<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Test\Cases\Utils;

use Hyperf\Extend\Utils\RSA;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class RSATest extends TestCase
{
    public function test加解密()
    {
        $str = '{"read_token":"testtoken"}';
        $this->assertEquals($str, RSA::decryptWithPublicKey(RSA::encryptWithPrivateKey($str)));
        $this->assertEquals($str, RSA::decryptWithPrivateKey(RSA::encryptWithPublicKey($str)));
    }
}
