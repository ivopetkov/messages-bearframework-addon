<?php

/*
 * Messages addon for Bear Framework
 * https://github.com/ivopetkov/messages-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

use BearFramework\App;

$app = App::get();
$context = $app->contexts->get(__FILE__);

$context->classes
        ->add('IvoPetkov\BearFrameworkAddons\Messages', 'classes/Messages.php')
        ->add('IvoPetkov\BearFrameworkAddons\Messages\*', 'classes/Messages/*.php');

$app->shortcuts
        ->add('messages', function () {
                return new IvoPetkov\BearFrameworkAddons\Messages();
        });
