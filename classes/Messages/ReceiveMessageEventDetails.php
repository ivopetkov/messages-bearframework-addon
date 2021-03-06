<?php

/*
 * Messages addon for Bear Framework
 * https://github.com/ivopetkov/messages-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons\Messages;

/**
 * @property string $threadID
 * @property string $userID
 * @property string $text
 * @property string $senderID
 */
class ReceiveMessageEventDetails
{

    use \IvoPetkov\DataObjectTrait;

    /**
     * 
     * @param string $threadID
     * @param string $userID
     * @param string $text
     * @param string $senderID
     */
    public function __construct(string $threadID, string $userID, string $text, string $senderID)
    {
        $this
            ->defineProperty('threadID', [
                'type' => 'string'
            ])
            ->defineProperty('userID', [
                'type' => 'string'
            ])
            ->defineProperty('text', [
                'type' => 'string'
            ])
            ->defineProperty('senderID', [
                'type' => 'string'
            ]);
        $this->threadID = $threadID;
        $this->userID = $userID;
        $this->text = $text;
        $this->senderID = $senderID;
    }
}
