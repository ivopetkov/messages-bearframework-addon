<?php

/*
 * Messages addon for Bear Framework
 * https://github.com/ivopetkov/messages-bearframework-addon
 * Copyright (c) 2017 Ivo Petkov
 * Free to use under the MIT license.
 */

/**
 * @runTestsInSeparateProcesses
 */
class DataTest extends BearFrameworkAddonTestCase
{

    /**
     * 
     */
    public function testCreate()
    {
        $app = $this->getApp();
        $threadID = $app->messages->createThread(['user1', 'user2']);
        $threadID2 = $app->messages->createThread(['user1', 'user2']);
        $this->assertTrue($threadID === $threadID2);

        $app->messages->add($threadID, 'user1', 'hi1');

        $user1Thread = $app->messages->getUserThread('user1', $threadID);
        $user2Thread = $app->messages->getUserThread('user2', $threadID);
        $this->assertTrue($user1Thread->id === $threadID);
        $this->assertTrue(array_search('user1', $user1Thread->usersIDs) !== false);
        $this->assertTrue(array_search('user2', $user1Thread->usersIDs) !== false);
        $this->assertTrue($user1Thread->messagesList[0]->userID === 'user1');
        $this->assertTrue($user1Thread->messagesList[0]->text === 'hi1');
        $this->assertTrue($user1Thread->status === 'read');
        $this->assertTrue($user2Thread->status === 'unread');

        $app->messages->markUserThreadAsRead('user1', $threadID);
        $user1Thread = $app->messages->getUserThread('user1', $threadID);
        $user2Thread = $app->messages->getUserThread('user2', $threadID);
        $this->assertTrue($user1Thread->status === 'read');
        $this->assertTrue($user2Thread->status === 'unread');
        $app->messages->markUserThreadAsRead('user2', $threadID);
        $user1Thread = $app->messages->getUserThread('user1', $threadID);
        $user2Thread = $app->messages->getUserThread('user2', $threadID);
        $this->assertTrue($user1Thread->status === 'read');
        $this->assertTrue($user2Thread->status === 'read');

        $app->messages->add($threadID, 'user2', 'hi2');
        $user2Thread = $app->messages->getUserThread('user2', $threadID);
        $this->assertTrue($user2Thread->id === $threadID);
        $this->assertTrue(array_search('user1', $user2Thread->usersIDs) !== false);
        $this->assertTrue(array_search('user2', $user2Thread->usersIDs) !== false);
        $this->assertTrue($user2Thread->messagesList[0]->userID === 'user1');
        $this->assertTrue($user2Thread->messagesList[0]->text === 'hi1');
        $this->assertTrue($user2Thread->messagesList[1]->userID === 'user2');
        $this->assertTrue($user2Thread->messagesList[1]->text === 'hi2');
    }

    /**
     * 
     */
    public function testUserList()
    {
        $app = $this->getApp();
        $thread1ID = $app->messages->createThread(['user1', 'user2']);
        $thread2ID = $app->messages->createThread(['user1', 'user3']);
        $app->messages->add($thread1ID, 'user1', 'hi user2');
        $app->messages->add($thread2ID, 'user1', 'hi user3');
        $app->messages->add($thread2ID, 'user3', 'hi user1');
        $list = $app->messages->getUserThreadsList('user1');
        $this->assertTrue($list->length === 2);
        $this->assertTrue($list[0]->status === 'read');
        $this->assertTrue($list[0]->messagesList[0]->userID === 'user1');
        $this->assertTrue($list[0]->messagesList[0]->text === 'hi user2');
        $this->assertTrue($list[1]->status === 'unread');
        $this->assertTrue($list[1]->messagesList[1]->userID === 'user3');
        $this->assertTrue($list[1]->messagesList[1]->text === 'hi user1');
    }

}
