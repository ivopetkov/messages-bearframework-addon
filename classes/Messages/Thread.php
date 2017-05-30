<?php

/*
 * Messages addon for Bear Framework
 * https://github.com/ivopetkov/messages-bearframework-addon
 * Copyright (c) 2017 Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons\Messages;

use BearFramework\App;

/**
 * @property-read array $usersIDs An array containing the users IDs that can participate in the thread.
 * @property-read \IvoPetkov\DataList|IvoPetkov\BearFrameworkAddons\Messages\Message[] $messagesList A list containing all messages in the thread.
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

        $getThreadData = function() {
            $app = App::get();
            $idMD5 = md5($this->id);
            $threadDataKey = 'messages/thread/' . substr($idMD5, 0, 2) . '/' . substr($idMD5, 2, 2) . '/' . $idMD5 . '.json';
            $threadDataValue = $app->data->getValue($threadDataKey);
            if ($threadDataValue !== null) {
                $threadData = json_decode($threadDataValue, true);
                if (is_array($threadData) && isset($threadData['id']) && $threadData['id'] === $this->id) {
                    return $threadData;
                }
                throw new \Exception('Corrupted data for thread ' . $this->id);
            }
            return null;
        };

        $this->defineProperty('usersIDs', [
            'init' => function() use ($getThreadData) {
                $threadData = $getThreadData();
                return isset($threadData['usersIDs']) ? $threadData['usersIDs'] : [];
            },
            'readonly' => true
        ]);

        $this->defineProperty('messagesList', [
            'init' => function() use ($getThreadData) {
                return new \IvoPetkov\DataList(function() use ($getThreadData) {
                            $result = [];
                            $threadData = $getThreadData();
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
        ]);

        $this->defineProperty('lastMessage', [
            'init' => function() use ($getThreadData) {
                $threadData = $getThreadData();
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
