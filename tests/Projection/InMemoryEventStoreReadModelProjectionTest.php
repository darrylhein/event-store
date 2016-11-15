<?php
/**
 * This file is part of the prooph/event-store.
 * (c) 2014-2016 prooph software GmbH <contact@prooph.de>
 * (c) 2015-2016 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStore\Projection;

use ArrayIterator;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Projection\InMemoryEventStoreReadModelProjection;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;
use ProophTest\EventStore\Mock\ReadModelProjectionMock;
use ProophTest\EventStore\Mock\UserCreated;
use ProophTest\EventStore\Mock\UsernameChanged;
use ProophTest\EventStore\TestCase;

class InMemoryEventStoreReadModelProjectionTest extends TestCase
{
    /**
     * @test
     */
    public function it_updates_read_model_using_when(): void
    {
        $this->prepareEventStream('user-123');

        $readModel = new ReadModelProjectionMock();

        $projection = new InMemoryEventStoreReadModelProjection($this->eventStore, 'test_projection', $readModel);

        $projection
            ->fromAll()
            ->when([
                UserCreated::class => function ($state, Message $event) {
                    $this->readModelProjection()->insert('name', $event->payload()['name']);
                },
                UsernameChanged::class => function ($state, Message $event) {
                    $this->readModelProjection()->update('name', $event->payload()['name']);
                }
            ])
            ->run();

        $this->assertEquals('Sascha', $readModel->read('name'));
    }

    /**
     * @test
     */
    public function it_updates_read_model_using_when_any(): void
    {
        $this->prepareEventStream('user-123');

        $readModel = new ReadModelProjectionMock();

        $projection = new InMemoryEventStoreReadModelProjection($this->eventStore, 'test_projection', $readModel);

        $projection
            ->init(function () {
                $this->readModelProjection()->insert('name', null);
                return new \stdClass();
            })
            ->fromStream('user-123')
            ->whenAny(function ($state, Message $event) {
                $this->readModelProjection()->update('name', $event->payload()['name']);
            }
            )
            ->run();

        $this->assertEquals('Sascha', $readModel->read('name'));
    }

    /**
     * @test
     */
    public function it_throws_exception_on_run_when_nothing_configured(): void
    {
        $this->expectException(RuntimeException::class);

        $readModel = new ReadModelProjectionMock();

        $query = new InMemoryEventStoreReadModelProjection($this->eventStore, 'test_projection', $readModel);
        $query->run();
    }

    private function prepareEventStream(string $name): void
    {
        $events = [];
        $events[] = UserCreated::with([
            'name' => 'Alex'
        ], 1);
        for ($i = 2; $i < 50; $i++) {
            $events[] = UsernameChanged::with([
                'name' => uniqid('name_')
            ], $i);
        }
        $events[] = UsernameChanged::with([
            'name' => 'Sascha'
        ], 50);

        $this->eventStore->create(new Stream(new StreamName($name), new ArrayIterator($events)));
    }
}
