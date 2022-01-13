<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Tests\Integration;

use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Tests\Fixtures\MoviesFixture;
use Laudis\Neo4j\Types\ArrayList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Path;

/**
 * @psalm-suppress all
 */
final class DriverParityTest extends EnvironmentAwareIntegrationTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        foreach (self::connectionAliases() as $alias) {
            $session = self::$client->getDriver($alias[0])->createSession();
            $session->run('MATCH (x) DETACH DELETE x', []);
            $session->run(MoviesFixture::CQL, []);
        }
    }

    protected static function formatter(): FormatterInterface
    {
        return SummarizedResultFormatter::create();
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testCanHandleMapLiterals(string $alias): void
    {
        $results = $this->getClient()->run('MATCH (n:Person)-[r:ACTED_IN]->(m) RETURN n, {movie: m, roles: r.roles} AS actInfo LIMIT 5', [], $alias);

        foreach ($results as $result) {
            $actorInfo = $result->get('actInfo');

            $this->assertInstanceOf(CypherMap::class, $actorInfo);
            $this->assertTrue($actorInfo->hasKey('roles'));
            $this->assertTrue($actorInfo->hasKey('movie'));
        }
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testComplex(string $alias): void
    {
        $results = $this->getClient()->run(<<<'CYPHER'
        MATCH (n:Person)-[r:ACTED_IN]->(m), p = () - [] -> () - [] -> ()
        RETURN n, p, {movie: m, roles: r.roles} AS actInfo, m
        LIMIT 1
        CYPHER, [], $alias);

        foreach ($results as $result) {
            $actorInfo = $result->get('actInfo');

            self::assertInstanceOf(CypherMap::class, $actorInfo);
            self::assertTrue($actorInfo->hasKey('roles'));
            self::assertTrue($actorInfo->hasKey('movie'));

            self::assertInstanceOf(ArrayList::class, $actorInfo->get('roles'));
            self::assertInstanceOf(Node::class, $actorInfo->get('movie'));
            self::assertInstanceOf(Path::class, $result->get('p'));
            self::assertInstanceOf(Node::class, $result->get('m'));
        }
    }
}
