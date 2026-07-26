<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Switon\Testing\MockLogger;

class MockLoggerTest extends TestCase
{
    public function testLogRecordsLevelMessageAndContext(): void
    {
        $logger = new MockLogger();

        $logger->info('hello', ['k' => 1]);

        self::assertCount(1, $logger->logs);
        self::assertSame(LogLevel::INFO, $logger->logs[0]['level']);
        self::assertSame('hello', $logger->logs[0]['message']);
        self::assertSame(['k' => 1], $logger->logs[0]['context']);
    }

    public function testLevelHelpersSupportAllLogLevelsAndEmptyQueries(): void
    {
        $logger = new MockLogger();

        self::assertNull($logger->getLastLog());
        self::assertSame([], $logger->getLogsByLevel(LogLevel::ERROR));

        $logger->emergency('em');
        $logger->alert('al');
        $logger->critical('cr');
        $logger->debug('de');
        $logger->log('custom', 'msg', ['id' => 7]);

        self::assertCount(5, $logger->logs);
        self::assertSame(LogLevel::EMERGENCY, $logger->logs[0]['level']);
        self::assertSame(LogLevel::ALERT, $logger->logs[1]['level']);
        self::assertSame(LogLevel::CRITICAL, $logger->logs[2]['level']);
        self::assertSame(LogLevel::DEBUG, $logger->logs[3]['level']);
        self::assertSame('custom', $logger->logs[4]['level']);
        self::assertTrue($logger->hasLog(LogLevel::ALERT, 'al'));
        self::assertFalse($logger->hasLog(LogLevel::INFO, 'missing'));
        self::assertSame($logger->logs[4], $logger->getLastLog());
        self::assertSame([$logger->logs[0]], $logger->getLogsByLevel(LogLevel::EMERGENCY));

        $logger->clear();
        self::assertSame([], $logger->logs);
        self::assertSame([], $logger->getLogsByLevel(LogLevel::DEBUG));
    }

    public function testGenericLogMethodStoresCustomLevel(): void
    {
        $logger = new MockLogger();

        $logger->log('custom', 'msg');

        self::assertSame('custom', $logger->logs[0]['level']);
        self::assertSame('msg', $logger->logs[0]['message']);
    }

    public function testClearRemovesAllEntries(): void
    {
        $logger = new MockLogger();
        $logger->warning('x');

        $logger->clear();

        self::assertSame([], $logger->logs);
    }

    public function testHasLogMatchesSubstringMessage(): void
    {
        $logger = new MockLogger();
        $logger->error('something failed badly');

        self::assertTrue($logger->hasLog(LogLevel::ERROR, 'failed'));
        self::assertFalse($logger->hasLog(LogLevel::ERROR, 'missing'));
    }

    public function testGetLastLogReturnsMostRecentEntry(): void
    {
        $logger = new MockLogger();
        $logger->debug('first');
        $logger->notice('second');

        $last = $logger->getLastLog();

        self::assertNotNull($last);
        self::assertSame(LogLevel::NOTICE, $last['level']);
        self::assertSame('second', $last['message']);
    }

    public function testGetLogsByLevelFiltersEntries(): void
    {
        $logger = new MockLogger();
        $logger->info('a');
        $logger->error('b');
        $logger->info('c');

        $infos = $logger->getLogsByLevel(LogLevel::INFO);

        self::assertCount(2, $infos);
        self::assertSame('a', $infos[0]['message']);
        self::assertSame('c', $infos[1]['message']);
    }
}
