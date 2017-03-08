<?php

/*
 * Messages addon for Bear Framework
 * https://github.com/ivopetkov/messages-bearframework-addon
 * Copyright (c) 2017 Ivo Petkov
 * Free to use under the MIT license.
 */

use BearFramework\App;

$app = App::get();
$context = $app->context->get(__FILE__);

$context->classes
        ->add('IvoPetkov\BearFrameworkAddons\Messages\Message', 'classes/Messages/Message.php')
        ->add('IvoPetkov\BearFrameworkAddons\Messages\Thread', 'classes/Messages/Thread.php')
        ->add('IvoPetkov\BearFrameworkAddons\Messages\UserThread', 'classes/Messages/UserThread.php')
        ->add('IvoPetkov\BearFrameworkAddons\Messages', 'classes/Messages.php');

$app->shortcuts
        ->add('messages', function() {
            return new IvoPetkov\BearFrameworkAddons\Messages();
        });
