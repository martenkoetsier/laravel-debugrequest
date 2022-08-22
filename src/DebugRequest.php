<?php

namespace App\Http\Middleware;

namespace Martenkoetsier\LaravelDebugrequest;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class DebugRequest {
	/**
	 * Whether or not to log.
	 * 
	 * @var boolean
	 */
	protected $debug;

	/**
	 * The minimum width of the messages (enclosed in line drawing)
	 * 
	 * @var integer
	 */
	protected $minimum_width;

	/**
	 * The maximum width of the messages (enclosed in line drawing)
	 * 
	 * @var integer
	 */
	protected $maximum_width;

	/**
	 * Maximum length after which request parameters are truncated before logging.
	 * 
	 * @var integer
	 */
	protected $maximum_parameter_length;

	/**
     * Whether the bound route parameters should be displayed by just the Model class name and identifying key (if it is
	 * an Eloquent Model), or as the full string representation. This is only relevant if the middleware is called after
	 * the SubstituteBindings middleware, which it is by default.
	 * 
	 * @var boolean
	 */
	protected $bound_parameters_as_key;

	/**
	 * Setup.
	 * 
	 * Derive some settings from the configuration (or use defaults).
	 */
	public function __construct() {
		$this->debug = config('app.debug') && config('debugrequest.enabled', true);
		$this->minimum_width = config('debugrequest.minimum_width', 48);
		$this->maximum_width = config('debugrequest.maximum_width', 196);
		$this->maximum_parameter_length = config('debugrequest.maximum_parameter_length', 256);
		$this->bound_parameters_as_key = config('debugrequest.bound_parameters_as_key', true);
	}

	/**
	 * Handle an incoming request.
	 * 
	 * If the app.debug configuration is not set to true, this middleware does nothing. Otherwise, some information on
	 * the request is printed into the log, using the 'logger' function.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param \Closure $next
	 * @return mixed
	 */
	public function handle(Request $request, Closure $next) {
		$start = null;
		if ($this->debug) {
			$start = microtime((true));
			$info = [];
			// route info
			$desc = "route: " . (Route::currentRouteName() ?? '(anonymous)');
			if (count(Route::getCurrentRoute()->parameterNames())) {
				$desc .= " [";
				$first = true;
				foreach (Route::getCurrentRoute()->parameterNames() as $key) {
					if (!$first) {
						$desc .= ", ";
					}
					$bound = $request->route($key);
					if ($this->bound_parameters_as_key && is_object($bound) && (is_a($bound, Model::class))) {
						$class = preg_replace('/^App\\\\Models\\\\/', '', get_class($bound));
						$desc .= "$key => $class({$bound->getKey()})";
					} else {
						$desc .= "$key => " . $bound;
					}
					$first = false;
				}
				$desc .= "]";
			}
			$info[] = $desc;
			// middleware info
			$info[] = "mw: " . implode(", ", Route::getCurrentRoute()->gatherMiddleware());
			// session info
			if ($request->hasSession()) {
				$info[] = sprintf("sid:%s u:%s", $request->session()->getId(), $request->user()->id ?? 'none');
			} else {
				$info[] = '(no session)';
			}
			// request info
			$request->collect()->whenNotEmpty(function () use (&$info) {
				$info[] = "BR|request parameters";
			})->each(function ($value, $key) use (&$info) {
				if ($key[0] !== '_') {
					$value = var_export($value, true);
					if (mb_strlen($value) > $this->maximum_parameter_length) {
						$value = mb_substr($value, 0, $this->maximum_parameter_length) . ' (…)';
					}
					if ($key === 'totp_password') {
						$info[] = "[$key] " . sprintf("%06d", $value);
					} elseif (stripos($key, 'password') !== false) {
						$info[] = "[$key] " . str_repeat("*", strlen($value));
					} else {
						$info[] = "[$key] $value";
					}
				}
			});

			$this->logInfo($info, "{$request->method()} " . Str::start($request->path(), '/'));
		}

		$return = $next($request);

		if ($this->debug) {
			if ($request->hasSession() && $request->session()->has('errors')) {
				$info = [];
				$info[] = "error(s) set in session";
				$info[] = "BR";
				$errors = $request->session()->get('errors');
				foreach ($errors->keys() as $key) {
					foreach ($errors->get($key) as $index => $value) {
						$info[] = "[$key][$index] " . var_export($value, true);
					}
				}
				$this->logInfo($info);
			}

			if ($start) {
				logger(sprintf("request handled in %.3fms", 1000 * (microtime(true) - $start)));
			}
		}

		return $return;
	}

	/**
	 * Log the lines in the given info using the Laravel 'logger' function.
	 * 
	 * Too long lines are wrapped, not-so-printable characters are replaced by \un where n is the Unicode code point.
	 * 
	 * The minimum and maximum width are expressed in character positions, and represent the actual space, i.e. they do
	 * not include the line drawing elements and padding.
	 * 
	 * @param string[] $info List of lines to log
	 * @param integer $min_w Default 40
	 * @param integer $max_w Default 120
	 */
	protected function logInfo(array $info, string $hd = '', string $ft = '') {
		$min_w = $this->minimum_width;
		$max_w = $this->maximum_width;
		$wrapped = [];
		foreach ($info as $line) {
			$wrapped = array_merge(
				$wrapped,
				explode("\n", wordwrap(str_replace("\n", "\n↳   ", $line), $max_w, "\n↳   ", true))
			);
		}
		$wrapped = array_map([self::class, 'encode'], $wrapped);

		if (Str::length($hd) > $max_w - 2) {
			$hd = Str::substr($hd, 0, $max_w - 3) . '…';
		}
		if (Str::length($ft) > $max_w - 2) {
			$ft = Str::substr($ft, 0, $max_w - 3) . '…';
		}
		$min_w = min($min_w, $max_w);
		$w = max($min_w, 2 + Str::length($hd), 2 + Str::length($ft));
		foreach ($wrapped as $line) {
			$w = max($w, Str::length($line));
		}
		logger("╔═" . self::padRight($hd ? "╡{$hd}╞" : "", $w + 1, "═") . "╗");
		foreach ($wrapped as $line) {
			if (Str::startsWith($line, 'BR')) {
				$msg = Str::substr($line, 3);
				if ($msg) {
					if (Str::length($msg) > $max_w - 2) {
						$msg = Str::substr($msg, 0, $max_w - 3) . '…';
					}
					logger("╟─" . ($msg ? "┤{$msg}├" : "──") . str_repeat("─", $w - Str::length($msg) - 1) . "╢");
				} else {
					logger("╟" . str_repeat("─", $w + 2) . "╢");
				}
			} else {
				logger("║ " . self::padRight($line, $w) . " ║");
			}
		}
		logger("╚═" . self::padRight($ft ? "╡{$ft}╞" : "", $w + 1, "═") . "╝");
	}

	/**
	 * Encode not-so-printable characters in the given text 'in' into code point indicators.
	 * 
	 * Each not-so-printable character is replaced by \un, where n is the Unicode code point, or by a relevant escape
	 * sequence such as '\t' for TAB, etc.
	 * 
	 * The not-so-printable code points include vertical whitespace, special horizontal whitespace, etc. In fact, the
	 * only characters left as-is, are those with unicode properties L (letters), N (numbers), P (punctuation), and S
	 * (Symbol) and the 'space' character (ASCII 32).
	 * 
	 * If the string array $except contains characters, these characters are left out of replacing and will thus end up
	 * in the final string.
	 * 
	 * @param string $in The string to encode
	 * @param string[] $except
	 * @return string
	 */
	protected static function encode(string $in, array $replacement = []): string {
		return preg_replace_callback('/[^\pL\pN\pP\pS ]/u', function ($m) {
			// using the json_encode function is a trick: it automatically converts the character to \u with the code
			// point, whereas e.g. bin2hex would convert to the hexadecimal code
			return trim(json_encode($m[0]), '"');
		}, str_replace(array_keys($replacement), array_values($replacement), $in));
	}

	/**
	 * Replace padding functions from \Illuminate\Support\Str as they are not fully multibyte safe.
	 * 
	 * @param string $value
	 * @param int $length
	 * @param string $pad
	 * @return string
	 */
	public static function padBoth($value, $length, $pad = ' ') {
		$short = max(0, $length - mb_strlen($value));
		$short_left = floor($short / 2);
		$short_right = ceil($short / 2);
		return
			mb_substr(str_repeat($pad, $short_left), 0, $short_left) .
			$value .
			mb_substr(str_repeat($pad, $short_right), 0, $short_right);
	}

	/**
	 * Replace padding functions from \Illuminate\Support\Str as they are not fully multibyte safe.
	 * 
	 * @param string $value
	 * @param int $length
	 * @param string $pad
	 * @return string
	 */
	public static function padRight($value, $length, $pad = ' ') {
		$short = max(0, $length - mb_strlen($value));
		return $value . mb_substr(str_repeat($pad, $short), 0, $short);
	}

	/**
	 * Replace padding functions from \Illuminate\Support\Str as they are not fully multibyte safe.
	 * 
	 * @param string $value
	 * @param int $length
	 * @param string $pad
	 * @return string
	 */
	public static function padLeft($value, $length, $pad = ' ') {
		$short = max(0, $length - mb_strlen($value));
		return mb_substr(str_repeat($pad, $short), 0, $short) . $value;
	}
}
