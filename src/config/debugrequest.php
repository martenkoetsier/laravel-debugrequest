<?php

/**
 * Configuration for the martenkoetsier/laravel-debugrequest package.
 */

return [
    /**
     * Whether or not messages are logged. This also depends on config('app.debug'), without which
     * logging is not performed.
     *
     * @var boolean $enabled
     */
    'enabled' => true,

    /**
     * The middleware is normally added to the 'web' and 'api' middleware groups (at the end). If
     * this behavior is not desired, remove the groups from this setting and attach the middelware
     * to routes or groups manually.
     * 
     * @var array
     */
    'middleware_groups' => ['web', 'api'],

    /**
     * The messages are logged in "boxes", drawn with box drawing characters. These boxes have a
     * defined minimum and maximum width. This is the width available to the messages, not including
     * the box drawing characters and padding.
     * 
     * @var integer $minimum_width (number of characters)
     * @var integer $maximum_width (number of characters)
     */
    'minimum_width' => 48,
    'maximum_width' => 196,

    /**
     * This package also logs request parameters. Sometimes, these parameters are rather large. With
     * this setting, the length of the logged value is limited. If the value exceeds this length, it
     * is simply truncated and a ' (…)' suffix is added.
     * 
     * @var integer $maximum_parameter_length (number of characters)
     */
    'maximum_parameter_length' => 256,
];
