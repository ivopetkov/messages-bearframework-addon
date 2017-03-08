<?php

/*
 * Messages addon for Bear Framework
 * https://github.com/ivopetkov/messages-bearframework-addon
 * Copyright (c) 2017 Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons\Messages;

class UserThread extends Thread
{

    /**
     *
     * @var string Status of the thread in the user's context. Available values: read, unread.
     */
    public $status = null;

}
