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
use IvoPetkov\Lock;

/**
 * Messages
 */
class Messages
{

    static private $cache = [];

    public function getUsersThreadsList(array $usersIDs): \IvoPetkov\DataList
    {
        $usersIDs = array_unique($usersIDs);
        return new \IvoPetkov\DataList(function(\IvoPetkov\DataListContext $context) use ($usersIDs) {
            $statusFilter = null;
            foreach ($context->filterByProperties as $index => $filterByProperty) {
                if ($filterByProperty->property === 'status' && $filterByProperty->operator === 'equal') {
                    if ($filterByProperty->value === 'read') {
                        $statusFilter = 'read';
                    } elseif ($filterByProperty->value === 'unread') {
                        $statusFilter = 'unread';
                    }
                    if ($statusFilter !== null) {
                        $filterByProperty->applied = true;
                    }
                }
            }
            $threadsLastUpdatedDates = [];
            foreach ($usersIDs as $userID) {
                $userData = $this->getUserData($userID);
                if (is_array($userData)) {
                    $userThreadsListData = $this->getUserThreadsListData($userID, true, $userData);
                    foreach ($userData['threads'] as $userThreadData) {
                        $threadID = $userThreadData['id'];
                        if (!isset($userThreadsListData['threads'][$threadID])) {
                            throw new \Exception('Should not get here');
                        }
                        $add = true;
                        if ($statusFilter !== null) {
                            $lastReadMessageID = isset($userThreadData['lastReadMessageID']) ? (string) $userThreadData['lastReadMessageID'] : '';
                            $read = $lastReadMessageID === (string) $userThreadsListData['threads'][$threadID][0];
                            $add = ($statusFilter === 'read' && $read) || ($statusFilter === 'unread' && !$read);
                        }
                        if ($add) {
                            $threadsLastUpdatedDates[$threadID] = $userThreadsListData['threads'][$threadID][1];
                        }
                    }
                }
            }
            arsort($threadsLastUpdatedDates);
            $result = [];
            foreach ($threadsLastUpdatedDates as $threadID => $lastUpdateDate) {
                $result[] = function() use ($userID, $threadID) {
                    return $this->getUserThread($userID, $threadID);
                };
            }
            return $result;
        });
    }

    /**
     * 
     * @param string $userID
     * @return \IvoPetkov\DataList|\IvoPetkov\BearFrameworkAddons\Messages\UserThread[]
     */
    public function getUserThreadsList(string $userID): \IvoPetkov\DataList
    {
        return $this->getUsersThreadsList([$userID]);
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
        if (is_array($userData)) {
            $userThreadsListData = $this->getUserThreadsListData($userID);
            foreach ($userData['threads'] as $threadData) {
                if ($threadData['id'] === $threadID) {
                    if (isset($userThreadsListData['threads'][$threadID])) {
                        $read = (isset($threadData['lastReadMessageID']) ? (string) $threadData['lastReadMessageID'] : '') === (string) $userThreadsListData['threads'][$threadID][0];
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

    private function removeThreadFromUserThreadsListData($userID, $threadID, $userData = null)
    {
        $tempUserThreadsListData = $this->getUserThreadsListData($userID, false, $userData);
        if (isset($tempUserThreadsListData['threads'][$threadID])) {
            unset($tempUserThreadsListData['threads'][$threadID]);
            $this->setUserThreadsListData($userID, $tempUserThreadsListData);
        }
    }

    public function deleteUserThread(string $userID, string $threadID)
    {
        $this->lockUserData($userID);
        $this->lockThreadData($threadID);
        $userData = $this->getUserData($userID);
        if (is_array($userData)) {
            $hasUserDataChange = false;
            foreach ($userData['threads'] as $i => $threadData) {
                if ($threadData['id'] === $threadID) {
                    unset($userData['threads'][$i]);
                    $hasUserDataChange = true;
                    break;
                }
            }
            if ($hasUserDataChange) {
                $this->setUserData($userID, $userData);
            }
            $this->removeThreadFromUserThreadsListData($userID, $threadID, $userData);
            $threadData = $this->getThreadData($threadID);
            if (is_array($threadData)) {
                $userIDIndex = array_search($userID, $threadData['usersIDs']);
                if ($userIDIndex !== false) {
                    unset($threadData['usersIDs'][$userIDIndex]);
                    if (empty($threadData['usersIDs'])) {
                        $this->deleteThreadData($threadID);
                    } else {
                        $threadData['usersIDs'] = array_values($threadData['usersIDs']);
                        foreach ($threadData['messages'] as $i => $message) {
                            if ($message['userID'] === $userID) {
                                $threadData['messages'][$i]['userID'] = null;
                            }
                        }
                        foreach ($threadData['usersIDs'] as $otherUserID) {
                            $this->removeThreadFromUserThreadsListData($otherUserID, $threadID);
                        }
                        $this->setThreadData($threadID, $threadData);
                    }
                }
            }
        }
        $this->unlockThreadData($threadID);
        $this->unlockUserData($userID);
    }

    public function markUserThreadAsRead(string $userID, string $threadID)
    {
        $threadData = $this->getThreadData($threadID);
        if (is_array($threadData)) {
            $lastMessage = end($threadData['messages']);
            $this->lockUserData($userID);
            $userData = $this->getUserData($userID);
            $hasChange = false;
            if (is_array($userData)) {
                foreach ($userData['threads'] as $i => $threadData) {
                    if ($threadData['id'] === $threadID) {
                        $lastMessageID = $lastMessage !== false ? (string) $lastMessage['id'] : '';
                        if (!isset($userData['threads'][$i]['lastReadMessageID']) || (string) $userData['threads'][$i]['lastReadMessageID'] !== $lastMessageID) {
                            $userData['threads'][$i]['lastReadMessageID'] = $lastMessageID;
                            $hasChange = true;
                        }
                        break;
                    }
                }
                if ($hasChange) {
                    $this->setUserData($userID, $userData);
                }
            }
            $this->unlockUserData($userID);
        }
    }

    /**
     * Returns array containing [threadID=>[lastMessageID, lastMessageDate, otherUsersIDs, lastReadMessageID]]
     * @param string $userID
     * @param bool $updateMissing
     * @param array $userData
     * @return type
     * @throws \Exception
     */
    private function getUserThreadsListData($userID, $updateMissing = true, $userData = null)
    {
        $app = App::get();
        $userIDMD5 = md5($userID);
        $cacheKey = 'userThreadsListData-' . $userID;
        if (isset(self::$cache[$cacheKey])) {
            $tempData = self::$cache[$cacheKey];
        } else {
            $tempUserThreadsListDataKey = '.temp/messages/userthreads/' . substr($userIDMD5, 0, 2) . '/' . substr($userIDMD5, 2, 2) . '/' . $userIDMD5 . '.json';
            $tempData = [];
            $tempDataValue = $app->data->getValue($tempUserThreadsListDataKey);
            if ($tempDataValue !== null) {
                try {
                    $_tempData = json_decode(gzuncompress($tempDataValue), true);
                } catch (\Exception $e) {
                    $_tempData = null;
                }
                if (is_array($_tempData) && isset($_tempData['id']) && $_tempData['id'] === $userID) {
                    $tempData = $_tempData;
                }
            }
            self::$cache[$cacheKey] = $tempData;
        }
        if (!isset($tempData['id'])) {
            $tempData['id'] = $userID;
        }
        if (!isset($tempData['threads'])) {
            $tempData['threads'] = [];
        }
        if ($userData === null) {
            $userData = $this->getUserData($userID);
        }
        if (!is_array($userData)) {
            throw new \Exception('Invalid user data (' . $userID . ')');
        }

        $result = [
            'id' => $userID,
            'threads' => []
        ];
        foreach ($userData['threads'] as $userThreadData) {
            $threadID = $userThreadData['id'];
            if (isset($tempData['threads'][$threadID])) {
                $result['threads'][$threadID] = $tempData['threads'][$threadID];
            } else {
                if ($updateMissing) {
                    $threadData = $this->getThreadData($threadID);
                    $otherUsersIDs = [];
                    if (is_array($threadData)) {
                        $otherUsersIDs = array_values(array_diff($threadData['usersIDs'], [$userID]));
                        $lastMessage = end($threadData['messages']);
                        if ($lastMessage !== false) {
                            $tempData['threads'][$threadID] = [
                                $lastMessage['id'], // last message id
                                $lastMessage['dateCreated'], // last message date
                                $otherUsersIDs // other users ids
                            ];
                        }
                    }
                    if (!isset($tempData['threads'][$threadID])) {
                        $tempData['threads'][$threadID] = [
                            '', // last message id
                            (isset($threadData['dateCreated']) ? $threadData['dateCreated'] : 0), // last message date
                            $otherUsersIDs // other users ids
                        ];
                    }
                    $this->setUserThreadsListData($userID, $tempData);
                    $result['threads'][$threadID] = $tempData['threads'][$threadID];
                }
            }
        }
        return $result;
    }

    private function setUserThreadsListData($userID, $data)
    {
        $app = App::get();
        $userIDMD5 = md5($userID);
        $tempUserThreadsListDataKey = '.temp/messages/userthreads/' . substr($userIDMD5, 0, 2) . '/' . substr($userIDMD5, 2, 2) . '/' . $userIDMD5 . '.json';
        if (empty($data['threads'])) {
            $app->data->delete($tempUserThreadsListDataKey);
        } else {
            $app->data->set($app->data->make($tempUserThreadsListDataKey, gzcompress(json_encode($data))));
        }
        $cacheKey = 'userThreadsListData-' . $userID;
        self::$cache[$cacheKey] = $data;
    }

    private function getUserData($userID)
    {
        $cacheKey = 'userData-' . $userID;
        if (isset(self::$cache[$cacheKey]) || array_key_exists($cacheKey, self::$cache)) { // the second check handles the null value
            return self::$cache[$cacheKey];
        } else {
            $app = App::get();
            $userIDMD5 = md5($userID);
            $userDataKey = 'messages/user/' . substr($userIDMD5, 0, 2) . '/' . substr($userIDMD5, 2, 2) . '/' . $userIDMD5 . '.json';
            $userDataValue = $app->data->getValue($userDataKey);
            if ($userDataValue !== null) {
                $userData = json_decode($userDataValue, true);
                if (is_array($userData) && isset($userData['id']) && $userData['id'] === $userID) {
                    self::$cache[$cacheKey] = $userData;
                    return $userData;
                }
                throw new \Exception('Corrupted data for user ' . $userID);
            }
            self::$cache[$cacheKey] = null;
            return null;
        }
    }

    private function setUserData($userID, $data)
    {
        $app = App::get();
        $userIDMD5 = md5($userID);
        $userDataKey = 'messages/user/' . substr($userIDMD5, 0, 2) . '/' . substr($userIDMD5, 2, 2) . '/' . $userIDMD5 . '.json';
        if (empty($data['threads'])) {
            $app->data->delete($userDataKey);
        } else {
            $app->data->set($app->data->make($userDataKey, json_encode($data)));
        }
        $cacheKey = 'userData-' . $userID;
        self::$cache[$cacheKey] = $data;
    }

    private function getThreadData($threadID)
    {
        $cacheKey = 'threadData-' . $threadID;
        if (isset(self::$cache[$cacheKey]) || array_key_exists($cacheKey, self::$cache)) { // the second check handles the null value
            return self::$cache[$cacheKey];
        } else {
            $app = App::get();
            $threadIDMD5 = md5($threadID);
            $threadDataKey = 'messages/thread/' . substr($threadIDMD5, 0, 2) . '/' . substr($threadIDMD5, 2, 2) . '/' . $threadIDMD5 . '.json';
            $threadDataValue = $app->data->getValue($threadDataKey);
            if ($threadDataValue !== null) {
                $threadData = json_decode($threadDataValue, true);
                if (is_array($threadData) && isset($threadData['id']) && $threadData['id'] === $threadID) {
                    self::$cache[$cacheKey] = $threadData;
                    return $threadData;
                }
                throw new \Exception('Corrupted data for thread ' . $threadID);
            }
            self::$cache[$cacheKey] = null;
            return null;
        }
    }

    private function deleteThreadData($threadID)
    {
        $cacheKey = 'threadData-' . $threadID;
        if (array_key_exists($cacheKey, self::$cache)) {
            unset(self::$cache[$cacheKey]);
        }
        $app = App::get();
        $threadIDMD5 = md5($threadID);
        $threadDataKey = 'messages/thread/' . substr($threadIDMD5, 0, 2) . '/' . substr($threadIDMD5, 2, 2) . '/' . $threadIDMD5 . '.json';
        $newThreadDataKey = '.recyclebin/messages/thread/' . substr($threadIDMD5, 0, 2) . '/' . substr($threadIDMD5, 2, 2) . '/' . $threadIDMD5 . '.json';
        $app->data->rename($threadDataKey, $newThreadDataKey);
    }

    private function setThreadData($threadID, $data)
    {
        $app = App::get();
        $threadIDMD5 = md5($threadID);
        $threadDataKey = 'messages/thread/' . substr($threadIDMD5, 0, 2) . '/' . substr($threadIDMD5, 2, 2) . '/' . $threadIDMD5 . '.json';
        $dataItem = $app->data->make($threadDataKey, json_encode($data));
        $app->data->set($dataItem);
        $cacheKey = 'threadData-' . $threadID;
        self::$cache[$cacheKey] = $data;
    }

    public function getThreadID(array $usersIDs, $createIfMissing = true): string // was createThread
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
        $usersIDs = array_values(array_unique($usersIDs));
        $firstUserID = $usersIDs[0];
        sort($usersIDs);
        $usersIDsAsJSON = json_encode($usersIDs);
        if ($createIfMissing) {
            $this->lockUserData($firstUserID);
        }
        $firstUserData = $getUserData($firstUserID);
        $userThreadsListData = $this->getUserThreadsListData($firstUserID, true, $firstUserData);
        if (isset($userThreadsListData['threads'])) {
            foreach ($userThreadsListData['threads'] as $threadID => $userThreadListData) {
                $threadUserIDs = $userThreadListData[2];
                $threadUserIDs[] = $firstUserID;
                $threadUserIDs = array_values($threadUserIDs);
                sort($threadUserIDs);
                if ($usersIDsAsJSON === json_encode($threadUserIDs)) {
                    if ($createIfMissing) {
                        $this->unlockUserData($firstUserID);
                    }
                    return $threadID;
                }
            }
        }
        if (!$createIfMissing) {
            return null;
        }
        $threadID = md5(uniqid() . $usersIDsAsJSON);
        if ($this->getThreadData($threadID) !== null) {
            throw new \Exception('Thread ID collision');
        }
        $this->lockThreadData($threadID);
        $threadData = [
            'id' => $threadID,
            'usersIDs' => $usersIDs,
            'dateCreated' => time(),
            'messages' => []
        ];
        $this->setThreadData($threadID, $threadData);
        $this->unlockThreadData($threadID);
        foreach ($usersIDs as $userID) {
            if ($userID === $firstUserID) {
                $userData = $firstUserData;
            } else {
                $this->lockUserData($userID);
                $userData = $getUserData($userID);
            }
            $userData['threads'][] = ['id' => $threadID];
            $this->setUserData($userID, $userData);
            $this->unlockUserData($userID);
        }
        return $threadID;
    }

    public function isUserThread(string $userID, string $threadID)
    {
        $userData = $this->getUserData($userID);
        if (is_array($userData)) {
            foreach ($userData['threads'] as $i => $userThreadData) {
                if ($userThreadData['id'] === $threadID) {
                    return true;
                }
            }
        }
        return false;
    }

    public function add(string $threadID, string $userID, string $text)
    {
        $app = App::get();
        $app->hooks->execute('messageAdd', $threadID, $userID, $text);
        $this->lockThreadData($threadID);
        $threadData = $this->getThreadData($threadID);
        if ($threadData === null) {
            $this->unlockThreadData($threadID);
            throw new \Exception('Invalid thread ' . $threadID);
        }
        if (array_search($userID, $threadData['usersIDs']) === false) {
            $this->unlockThreadData($threadID);
            throw new \Exception('Invalid thread user ' . $threadID . ', ' . $userID);
        }
        $messageID = md5(uniqid());
        $messageTime = time();
        $threadData['messages'][] = [
            'id' => $messageID,
            'userID' => $userID,
            'dateCreated' => $messageTime,
            'text' => $text
        ];
        $this->setThreadData($threadID, $threadData);
        $this->unlockThreadData($threadID);
        $this->lockUserData($userID);
        $userData = $this->getUserData($userID);
        if (is_array($userData)) {
            foreach ($userData['threads'] as $i => $userThreadData) {
                if ($userThreadData['id'] === $threadID) {
                    $userData['threads'][$i]['lastReadMessageID'] = $messageID;
                }
            }
            $this->setUserData($userID, $userData);
        }
        $this->unlockUserData($userID);

        foreach ($threadData['usersIDs'] as $otherUserID) {
            $tempUserThreadsListData = $this->getUserThreadsListData($otherUserID, false, $userID === $otherUserID ? $userData : null);
            $tempUserThreadsListData['threads'][$threadID] = [
                $messageID, // last message id
                $messageTime, // last message time
                array_values(array_diff($threadData['usersIDs'], [$otherUserID])) // other users ids
            ];
            $this->setUserThreadsListData($otherUserID, $tempUserThreadsListData);
        }
        $app->hooks->execute('messageAdded', $threadID, $userID, $text);
        foreach ($threadData['usersIDs'] as $otherUserID) {
            if ($userID !== $otherUserID) {
                $app->hooks->execute('messageReceived', $otherUserID, $threadID, $userID, $text);
            }
        }
    }

    private function lockThreadData($threadID)
    {
        Lock::acquire('messages.thread.' . md5($threadID));
    }

    private function unlockThreadData($threadID)
    {
        Lock::release('messages.thread.' . md5($threadID));
    }

    private function lockUserData($userID)
    {
        Lock::acquire('messages.user.' . md5($userID));
    }

    private function unlockUserData($userID)
    {
        Lock::release('messages.user.' . md5($userID));
    }

}
