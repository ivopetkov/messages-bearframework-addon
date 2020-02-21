<?php

/*
 * Messages addon for Bear Framework
 * https://github.com/ivopetkov/messages-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons\Messages\Internal;

use BearFramework\App;

class Utilities
{

    /**
     * Global cache
     * 
     * @var array
     */
    static public $cache = [];


    /**
     * Clears the data cache
     *
     * @return void
     */
    static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * 
     * @param string $threadID
     * @return array|null
     * @throws \Exception
     */
    static function getThreadData(string $threadID): ?array
    {
        $cacheKey = 'threadData-' . $threadID;
        if (isset(Utilities::$cache[$cacheKey]) || array_key_exists($cacheKey, Utilities::$cache)) { // the second check handles the null value
            return Utilities::$cache[$cacheKey];
        } else {
            $app = App::get();
            $threadDataKey = self::getThreadDataKey($threadID);
            $threadDataValue = $app->data->getValue($threadDataKey);
            if ($threadDataValue !== null) {
                $threadData = json_decode($threadDataValue, true);
                if (is_array($threadData) && isset($threadData['id']) && $threadData['id'] === $threadID) {
                    Utilities::$cache[$cacheKey] = $threadData;
                    return $threadData;
                }
                throw new \Exception('Corrupted data for thread ' . $threadID);
            }
            Utilities::$cache[$cacheKey] = null;
            return null;
        }
    }

    /**
     * 
     *
     * @param string $threadID
     * @return string
     */
    static function getThreadDataKey(string $threadID): string
    {
        $threadIDMD5 = md5($threadID);
        return 'messages/thread/' . substr($threadIDMD5, 0, 2) . '/' . substr($threadIDMD5, 2, 2) . '/' . $threadIDMD5 . '.json';
    }
}
