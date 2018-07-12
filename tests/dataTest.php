<?php

/*
 * Messages addon for Bear Framework
 * https://github.com/ivopetkov/messages-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

/**
 * @runTestsInSeparateProcesses
 */
class DataTest extends BearFramework\AddonTests\PHPUnitTestCase
{

    /**
     * 
     */
    public function testCreate()
    {
        $app = $this->getApp();
        $threadID = $app->messages->getThreadID(['user1', 'user2']);
        $threadID2 = $app->messages->getThreadID(['user1', 'user2']);
        $this->assertTrue($threadID === $threadID2);

        $user1Thread = $app->messages->getUserThread('user1', $threadID);
        $this->assertTrue($user1Thread->lastMessage === null);

        $app->messages->add($threadID, 'user1', 'hi1');
        sleep(1); //added for sorting precision

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
        sleep(1); //added for sorting precision
        $user2Thread = $app->messages->getUserThread('user2', $threadID);
        $this->assertTrue($user2Thread->id === $threadID);
        $this->assertTrue(array_search('user1', $user2Thread->usersIDs) !== false);
        $this->assertTrue(array_search('user2', $user2Thread->usersIDs) !== false);
        $this->assertTrue($user2Thread->messagesList[0]->userID === 'user1');
        $this->assertTrue($user2Thread->messagesList[0]->text === 'hi1');
        $this->assertTrue($user2Thread->messagesList[1]->userID === 'user2');
        $this->assertTrue($user2Thread->messagesList[1]->text === 'hi2');
        $this->assertTrue($user2Thread->lastMessage->text === 'hi2');
    }

    /**
     * 
     */
    public function testUserList()
    {
        $app = $this->getApp();
        $thread1ID = $app->messages->getThreadID(['user1', 'user2']);
        $thread2ID = $app->messages->getThreadID(['user1', 'user3']);
        $app->messages->add($thread1ID, 'user1', 'hi user2');
        sleep(1); //added for sorting precision
        $app->messages->add($thread2ID, 'user1', 'hi user3');
        sleep(1); //added for sorting precision
        $app->messages->add($thread2ID, 'user3', 'hi user1');
        sleep(1); //added for sorting precision
        $list = $app->messages->getUserThreadsList('user1');
        $this->assertTrue($list->length === 2);
        $this->assertTrue($list[0]->status === 'unread');
        $this->assertTrue($list[0]->messagesList[1]->userID === 'user3');
        $this->assertTrue($list[0]->messagesList[1]->text === 'hi user1');
        $this->assertTrue($list[1]->status === 'read');
        $this->assertTrue($list[1]->messagesList[0]->userID === 'user1');
        $this->assertTrue($list[1]->messagesList[0]->text === 'hi user2');
    }

    /**
     * 
     */
    public function testUsersList()
    {
        $app = $this->getApp();

        $threadID = $app->messages->getThreadID(['user1', 'user2']);
        $app->messages->add($threadID, 'user1', 'hi user2');
        sleep(1); //added for sorting precision
        $threadID = $app->messages->getThreadID(['user1', 'user3']);
        $app->messages->add($threadID, 'user1', 'hi user3');
        sleep(1); //added for sorting precision

        $threadID = $app->messages->getThreadID(['userA', 'user2']);
        $app->messages->add($threadID, 'userA', 'hi user2, its userA');
        sleep(1); //added for sorting precision
        $threadID = $app->messages->getThreadID(['userA', 'userB']);
        $app->messages->add($threadID, 'userA', 'hi userB');
        sleep(1); //added for sorting precision

        $list = $app->messages->getUsersThreadsList(['user1', 'userA']);
        $this->assertTrue($list->length === 4);
        $this->assertTrue($list[0]->messagesList[0]->text === 'hi userB');
        $this->assertTrue($list[1]->messagesList[0]->text === 'hi user2, its userA');
        $this->assertTrue($list[2]->messagesList[0]->text === 'hi user3');
        $this->assertTrue($list[3]->messagesList[0]->text === 'hi user2');
    }

}
