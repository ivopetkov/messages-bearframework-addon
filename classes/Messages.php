<?php

/*
 * Messages addon for Bear Framework
 * https://github.com/ivopetkov/messages-bearframework-addon
 * Copyright (c) 2017 Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons;

use BearFramework\App;
use IvoPetkov\BearFrameworkAddons\Messages\UserThread;

/**
 * Messages
 */
class Messages
{

    /**
     * 
     * @param string $userID
     * @return \IvoPetkov\DataList|\IvoPetkov\BearFrameworkAddons\Messages\UserThread[]
     */
    public function getUserThreadsList(string $userID): \IvoPetkov\DataList
    {
        return new \IvoPetkov\DataList(function() use ($userID) {
            $result = [];
            $userData = $this->getUserData($userID);
            if (is_array($userData)) {
                foreach ($userData['threads'] as $threadData) {
                    $result[] = $this->getUserThread($userID, $threadData['id']);
                }
            }
            return $result;
        });
    }

    /**
     * 
     * @param string $userID
     * @param string $threadID
     * @return IvoPetkov\BearFrameworkAddons\Messages\UserThread
     */
    public function getUserThread(string $userID, string $threadID): \IvoPetkov\BearFrameworkAddons\Messages\UserThread
    {
        $userThread = new UserThread();
        $userThread->id = $threadID;
        $read = true;
        $userData = $this->getUserData($userID);
        $tempUserData = $this->getTempUserData($userID);
        if (is_array($userData)) {
            foreach ($userData['threads'] as $threadData) {
                if ($threadData['id'] === $threadID) {
                    if (isset($threadData['lastReadMessageID'], $tempUserData['threadsLastMessagesIDs'][$threadID])) {
                        $read = $threadData['lastReadMessageID'] === $tempUserData['threadsLastMessagesIDs'][$threadID];
                        break;
                    } else {
                        $read = false;
                    }
                }
            }
        }
        $userThread->status = $read ? 'read' : 'unread';
        return $userThread;
    }

    public function markUserThreadAsRead(string $userID, string $threadID)
    {
        $threadData = $this->getThreadData($threadID);
        if (is_array($threadData)) {
            $lastMessage = end($threadData['messages']);
            if ($lastMessage !== false) {
                $userData = $this->getUserData($userID);
                if (is_array($userData)) {
                    foreach ($userData['threads'] as $i => $threadData) {
                        if ($threadData['id'] === $threadID) {
                            $userData['threads'][$i]['lastReadMessageID'] = $lastMessage['id'];
                            break;
                        }
                    }
                    $this->setUserData($userID, $userData);
                }
            }
        }
    }

    private function getTempUserData($userID)
    {
        $app = App::get();
        $tempUserDataKey = '.temp/messages/user/' . md5($userID) . '.json';
        $tempUserDataValue = $app->data->getValue($tempUserDataKey);
        if ($tempUserDataValue !== null) {
            $tempUserData = json_decode($tempUserDataValue, true);
            if (is_array($tempUserData) && isset($tempUserData['id']) && $tempUserData['id'] === $userID) {
                return $tempUserData;
            }
        }
        $tempUserData = [];
        $tempUserData['id'] = $userID;
        $tempUserData['threadsLastMessagesIDs'] = [];

        $userData = $this->getUserData($userID);
        foreach ($userData['threads'] as $threadData) {
            $threadData = $this->getThreadData($threadData['id']);
            $lastMessage = end($threadData['messages']);
            $tempUserData['threadsLastMessagesIDs'][$threadData['id']] = $lastMessage !== false ? $lastMessage['id'] : null;
        }
        $this->setTempUserData($userID, $tempUserData);

        return $tempUserData;
    }

    private function setTempUserData($userID, $data)
    {
        $app = App::get();
        $tempUserDataKey = '.temp/messages/user/' . md5($userID) . '.json';
        $dataItem = $app->data->make($tempUserDataKey, json_encode($data));
        $app->data->set($dataItem);
    }

    private function getUserData($userID)
    {
        $app = App::get();
        $userDataKey = 'messages/user/' . md5($userID) . '.json';
        $userDataValue = $app->data->getValue($userDataKey);
        if ($userDataValue !== null) {
            $userData = json_decode($userDataValue, true);
            if (is_array($userData) && isset($userData['id']) && $userData['id'] === $userID) {
                return $userData;
            }
            throw new \Exception('Corrupted data for user ' . $userID);
        }
        return null;
    }

    private function setUserData($userID, $data)
    {
        $app = App::get();
        $userDataKey = 'messages/user/' . md5($userID) . '.json';
        $dataItem = $app->data->make($userDataKey, json_encode($data));
        $app->data->set($dataItem);
    }

    private function getThreadData($threadID)
    {
        $app = App::get();
        $threadDataKey = 'messages/thread/' . md5($threadID) . '.json';
        $threadDataValue = $app->data->getValue($threadDataKey);
        if ($threadDataValue !== null) {
            $threadData = json_decode($threadDataValue, true);
            if (is_array($threadData) && isset($threadData['id']) && $threadData['id'] === $threadID) {
                return $threadData;
            }
            throw new \Exception('Corrupted data for thread ' . $threadID);
        }
        return null;
    }

    private function setThreadData($threadID, $data)
    {
        $app = App::get();
        $threadDataKey = 'messages/thread/' . md5($threadID) . '.json';
        $dataItem = $app->data->make($threadDataKey, json_encode($data));
        $app->data->set($dataItem);
    }

    public function createThread(array $usersIDs): string
    {
        if (empty($usersIDs)) {
            throw new \Exception('usersIDs cannot be empty');
        }
        $getUserData = function($userID) {
            $data = $this->getUserData($userID);
            if (!is_array($data)) {
                $data = [];
                $data['id'] = $userID;
                $data['threads'] = [];
            }
            return $data;
        };
        $usersIDs = array_values($usersIDs);
        sort($usersIDs);
        $firstUserID = $usersIDs[0];
        $usersIDsAsJSON = json_encode($usersIDs);
        $firstUserData = $getUserData($firstUserID);
        foreach ($firstUserData['threads'] as $threadData) {
            $threadID = $threadData['id'];
            $threadData = $this->getThreadData($threadID);
            if (is_array($threadData) && isset($threadData['usersIDs'])) {
                if ($usersIDsAsJSON === json_encode($threadData['usersIDs'])) {
                    return $threadID;
                }
            }
        }
        $threadID = md5(uniqid() . $usersIDsAsJSON);
        if ($this->getThreadData($threadID) !== null) {
            throw new \Exception('Thread ID collision');
        }
        $threadData = [
            'id' => $threadID,
            'usersIDs' => $usersIDs,
            'messages' => []
        ];
        $this->setThreadData($threadID, $threadData);
        foreach ($usersIDs as $userID) {
            if ($userID === $firstUserID) {
                $userData = $firstUserData;
            } else {
                $userData = $getUserData($userID);
            }
            $userData['threads'][] = ['id' => $threadID];
            $this->setUserData($userID, $userData);
        }
        return $threadID;
    }

    public function add(string $threadID, string $userID, string $text)
    {
        $threadData = $this->getThreadData($threadID);
        if ($threadData === null) {
            throw new \Exception('Invalid thread ' . $threadID);
        }
        $messageID = md5(uniqid());
        $threadData['messages'][] = [
            'id' => $messageID,
            'userID' => $userID,
            'dateCreated' => time(),
            'text' => $text
        ];
        $userData = $this->getUserData($userID);
        if (is_array($userData)) {
            foreach ($userData['threads'] as $i => $_threadData) {
                if ($_threadData['id'] === $threadID) {
                    $userData['threads'][$i]['lastReadMessageID'] = $messageID;
                }
            }
            $this->setUserData($userID, $userData);
        }
        $this->setThreadData($threadID, $threadData);
        foreach ($threadData['usersIDs'] as $userID) {
            $tempUserData = $this->getTempUserData($userID);
            $tempUserData['threadsLastMessagesIDs'][$threadID] = $messageID;
            $this->setTempUserData($userID, $tempUserData);
        }
    }

}
