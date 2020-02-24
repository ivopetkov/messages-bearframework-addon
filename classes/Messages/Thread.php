<?php

/*
 * Messages addon for Bear Framework
 * https://github.com/ivopetkov/messages-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons\Messages;

use IvoPetkov\BearFrameworkAddons\Messages\Internal\Utilities;

/**
 * @property-read array $usersIDs An array containing the users IDs that can participate in the thread.
 * @property-read \BearFramework\DataList|IvoPetkov\BearFrameworkAddons\Messages\Message[] $messagesList A list containing all messages in the thread.
 * @property-read ?BearFrameworkAddons\Messages\Message $lastMessage The last message in the thread
 */
class Thread
{

    use \IvoPetkov\DataObjectTrait;

    /**
     *
     * @var string The id of the thread.
     */
    public $id = null;

    function __construct()
    {

        $this
            ->defineProperty('usersIDs', [
                'init' => function () {
                    $threadData = Utilities::getThreadData($this->id);
                    return isset($threadData['usersIDs']) ? $threadData['usersIDs'] : [];
                },
                'readonly' => true
            ])
            ->defineProperty('messagesList', [
                'init' => function () {
                    return new \BearFramework\DataList(function () {
                        $result = [];
                        $threadData = Utilities::getThreadData($this->id);
                        if (is_array($threadData) && isset($threadData['messages'])) {
                            foreach ($threadData['messages'] as $messageData) {
                                $message = new Message();
                                $message->id = $messageData['id'];
                                $message->userID = $messageData['userID'];
                                $message->text = $messageData['text'];
                                $message->dateCreated = $messageData['dateCreated'];
                                $result[] = $message;
                            }
                        }
                        return $result;
                    });
                },
                'readonly' => true
            ])
            ->defineProperty('lastMessage', [
                'init' => function () {
                    $threadData = Utilities::getThreadData($this->id);
                    if (is_array($threadData) && isset($threadData['messages'])) {
                        $lastMessageData = end($threadData['messages']);
                        if ($lastMessageData !== false) {
                            $message = new Message();
                            $message->id = $lastMessageData['id'];
                            $message->userID = $lastMessageData['userID'];
                            $message->text = $lastMessageData['text'];
                            $message->dateCreated = $lastMessageData['dateCreated'];
                            return $message;
                        }
                    }
                    return null;
                },
                'readonly' => true
            ]);
    }
}
