<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers \Odahcam\Router
 */
final class NamespaceTest extends TestCase
{

    public function testCanInstantiateRouter()
    {
        new \Odahcam\Router();
    }
}
