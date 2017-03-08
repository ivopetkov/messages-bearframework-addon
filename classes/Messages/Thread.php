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
            $threadDataKey = 'messages/thread/' . md5($this->id) . '.json';
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
    }

}
