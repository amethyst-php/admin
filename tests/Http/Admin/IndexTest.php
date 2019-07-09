<?php

namespace Amethyst\Tests\Http\Admin;

use Amethyst\Tests\BaseTest;

class IndexTest extends BaseTest
{
    /**
     * Test common requests.
     */
    public function testSignIn()
    {
        $response = $this->get(route('admin.index'));
        $response->assertStatus(200);

        print_r(json_decode($response->getContent()));
    }
}
