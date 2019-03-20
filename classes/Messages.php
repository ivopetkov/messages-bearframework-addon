<?php

/*
 * Messages addon for Bear Framework
 * https://github.com/ivopetkov/messages-bearframework-addon
 * Copyright (c) Ivo Petkov
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

    use \BearFramework\EventsTrait;

    /**
     *
     * @var array 
     */
    static private $cache = [];

    /**
     * 
     * @param array $usersIDs
     * @param array $options Available options: includeEmptyThreads
     * @return \BearFramework\DataList
     */
    public function getUsersThreadsList(array $usersIDs, array $options = []): \BearFramework\DataList
    {
        $includeEmptyThreads = isset($options['includeEmptyThreads']) && (int) $options['includeEmptyThreads'] > 0;
        $usersIDs = array_unique($usersIDs);
        return new \BearFramework\DataList(function(\BearFramework\DataList\Context $context) use ($usersIDs, $includeEmptyThreads) {
            $statusFilter = null;
            foreach ($context->actions as $action) {
                if ($action instanceof \BearFramework\DataList\FilterByAction) {
                    if ($action->property === 'status' && $action->operator === 'equal') {
                        if ($action->value === 'read') {
                            $statusFilter = 'read';
                        } elseif ($action->value === 'unread') {
                            $statusFilter = 'unread';
                        }
                    }
                }
            }
            $threadsLastUpdatedDates = [];
            $threadsUsers = [];
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
                        $lastMessageID = (string) $userThreadsListData['threads'][$threadID][0];
                        $lastUpdateDate = $userThreadsListData['threads'][$threadID][1];
                        if (strlen($lastMessageID) === 0 && !$includeEmptyThreads) {
                            $add = false;
                        }
                        if ($add) {
                            if ($statusFilter !== null) {
                                $lastReadMessageID = isset($userThreadData['lastReadMessageID']) ? (string) $userThreadData['lastReadMessageID'] : '';
                                $read = $lastReadMessageID === $lastMessageID;
                                $add = ($statusFilter === 'read' && $read) || ($statusFilter === 'unread' && !$read);
                            }
                        }
                        if ($add) {
                            $threadsLastUpdatedDates[$threadID] = $lastUpdateDate;
                            $threadsUsers[$threadID] = $userID;
                        }
                    }
                }
            }
            arsort($threadsLastUpdatedDates);
            $result = [];
            foreach ($threadsLastUpdatedDates as $threadID => $lastUpdateDate) {
                $userID = $threadsUsers[$threadID];
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
     * @return \BearFramework\DataList|\IvoPetkov\BearFrameworkAddons\Messages\UserThread[]
     */
    public function getUserThreadsList(string $userID): \BearFramework\DataList
    {
        return $this->getUsersThreadsList([$userID]);
    }

    /**
     * 
     * @param string $userID
     * @param string $threadID
     * @return IvoPetkov\BearFrameworkAddons\Messages\UserThread|null
     */
    public function getUserThread(string $userID, string $threadID): ?\IvoPetkov\BearFrameworkAddons\Messages\UserThread
    {
        $userData = $this->getUserData($userID);
        if (is_array($userData)) {
            $userThreadsListData = $this->getUserThreadsListData($userID);
            foreach ($userData['threads'] as $threadData) {
                if ($threadData['id'] === $threadID) {
                    $userThread = new UserThread();
                    $userThread->id = $threadID;
                    $read = true;
                    if (isset($userThreadsListData['threads'][$threadID])) {
                        $lastMessageID = (string) $userThreadsListData['threads'][$threadID][0];
                        $userThread->lastUpdateDate = $userThreadsListData['threads'][$threadID][1];
                        $read = (isset($threadData['lastReadMessageID']) ? (string) $threadData['lastReadMessageID'] : '') === $lastMessageID;
                    } else {
                        $read = false;
                    }
                    $userThread->status = $read ? 'read' : 'unread';
                    return $userThread;
                }
            }
        }
        return null;
    }

    /**
     * 
     * @param string $userID
     * @param string $threadID
     * @param array|null $userData
     * @return void
     */
    private function removeThreadFromUserThreadsListData(string $userID, string $threadID, $userData = null): void
    {
        $tempUserThreadsListData = $this->getUserThreadsListData($userID, false, $userData);
        if (isset($tempUserThreadsListData['threads'][$threadID])) {
            unset($tempUserThreadsListData['threads'][$threadID]);
            $this->setUserThreadsListData($userID, $tempUserThreadsListData);
        }
    }

    /**
     * 
     * @param string $userID
     * @param string $threadID
     * @return void
     */
    public function deleteUserThread(string $userID, string $threadID): void
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

    /**
     * 
     * @param string $userID
     * @param string $threadID
     * @return void
     */
    public function markUserThreadAsRead(string $userID, string $threadID): void
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
     * @return array
     * @throws \Exception
     */
    private function getUserThreadsListData($userID, $updateMissing = true, $userData = null): array
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

        $result = [
            'id' => $userID,
            'threads' => []
        ];
        if ($userData === null) {
            return $result;
        }
        if (!is_array($userData)) {
            throw new \Exception('Invalid user data (' . $userID . ')');
        }
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

    /**
     * 
     * @param string $userID
     * @param array $data
     * @return void
     */
    private function setUserThreadsListData(string $userID, array $data): void
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

    /**
     * 
     * @param string $userID
     * @return array|null
     * @throws \Exception
     */
    private function getUserData(string $userID): ?array
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

    /**
     * 
     * @param string $userID
     * @param array $data
     * @return void
     */
    private function setUserData(string $userID, array $data): void
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

    /**
     * 
     * @param string $threadID
     * @return array|null
     * @throws \Exception
     */
    private function getThreadData(string $threadID): ?array
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

    /**
     * 
     * @param string $threadID
     * @return void
     */
    private function deleteThreadData(string $threadID): void
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

    /**
     * 
     * @param string $threadID
     * @param array $data
     * @return void
     */
    private function setThreadData(string $threadID, array $data): void
    {
        $app = App::get();
        $threadIDMD5 = md5($threadID);
        $threadDataKey = 'messages/thread/' . substr($threadIDMD5, 0, 2) . '/' . substr($threadIDMD5, 2, 2) . '/' . $threadIDMD5 . '.json';
        $dataItem = $app->data->make($threadDataKey, json_encode($data));
        $app->data->set($dataItem);
        $cacheKey = 'threadData-' . $threadID;
        self::$cache[$cacheKey] = $data;
    }

    /**
     * 
     * @param array $usersIDs
     * @param bool $createIfMissing
     * @return string|null
     * @throws \Exception
     */
    public function getThreadID(array $usersIDs, bool $createIfMissing = true): ?string
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

    /**
     * 
     * @param string $userID
     * @param string $threadID
     * @return bool
     */
    public function isUserThread(string $userID, string $threadID): bool
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

    /**
     * 
     * @param string $threadID
     * @param string $userID
     * @param string $text
     * @return void
     * @throws \Exception
     */
    public function add(string $threadID, string $userID, string $text): void
    {
        if ($this->hasEventListeners('beforeAddMessage')) {
            $eventDetails = new \IvoPetkov\BearFrameworkAddons\Messages\BeforeAddMessageEventDetails($threadID, $userID, $text);
            $this->dispatchEvent('beforeAddMessage', $eventDetails);
        }
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
        if ($this->hasEventListeners('addMessage')) {
            $eventDetails = new \IvoPetkov\BearFrameworkAddons\Messages\AddMessageEventDetails($threadID, $userID, $text);
            $this->dispatchEvent('addMessage', $eventDetails);
        }
        if ($this->hasEventListeners('receiveMessage')) {
            foreach ($threadData['usersIDs'] as $otherUserID) {
                if ($userID !== $otherUserID) {
                    $eventDetails = new \IvoPetkov\BearFrameworkAddons\Messages\ReceiveMessageEventDetails($threadID, $otherUserID, $text, $userID);
                    $this->dispatchEvent('receiveMessage', $eventDetails);
                }
            }
        }
    }

    /**
     * 
     * @param string $threadID
     * @return void
     */
    private function lockThreadData(string $threadID): void
    {
        $app = App::get();
        $app->locks->acquire('messages.thread.' . md5($threadID));
    }

    /**
     * 
     * @param string $threadID
     * @return void
     */
    private function unlockThreadData(string $threadID): void
    {
        $app = App::get();
        $app->locks->release('messages.thread.' . md5($threadID));
    }

    /**
     * 
     * @param string $userID
     * @return void
     */
    private function lockUserData(string $userID): void
    {
        $app = App::get();
        $app->locks->acquire('messages.user.' . md5($userID));
    }

    /**
     * 
     * @param string $userID
     * @return void
     */
    private function unlockUserData(string $userID): void
    {
        $app = App::get();
        $app->locks->release('messages.user.' . md5($userID));
    }

}
