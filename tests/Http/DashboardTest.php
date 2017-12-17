<?php

namespace OpenDominion\Tests\Http;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use OpenDominion\Tests\AbstractBrowserKitTestCase;
use OpenDominion\Tests\AbstractTestCase;

class DashboardTest extends AbstractTestCase
{
    use DatabaseMigrations;

    public function testDashboardPage()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/auth/login');

        $this->createAndImpersonateUser();

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }
}
