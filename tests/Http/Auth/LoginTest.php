<?php

namespace OpenDominion\Tests\Http\Auth;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use OpenDominion\Tests\AbstractTestCase;

class LoginTest extends AbstractTestCase
{
    use DatabaseMigrations;

    public function testLoginPage()
    {
        $response = $this->get('/auth/login')
            ->assertStatus(200);
    }

    public function testUserCanLogin()
    {
        $user = $this->createUser('secret');

        $response = $this->get('/auth/login')
            ->assertSeeText('Login');

        $data = [
            'email' => $user->email,
            'password' => 'secret'                
        ];
        $response = $this->post('/auth/login', $data)
            ->assertRedirect('/dashboard');
        // todo: see logged in user == $user
    }

    public function testUserCanLogout()
    {
        $user = $this->createUser('secret');

        $response = $this->actingAs($user)
            ->get('/dashboard')
            ->assertSeeText('Dashboard');
        
        $response = $this->actingAs($user)
            ->post('/auth/logout');
        
        $response->assertSeeText('You have been logged out.');
    }
    
    public function testUserCantLoginWithInvalidCredentials()
    {   
        $data = [
            'email' => 'nonexistant@example.com',
            'password' => 'somepassword'                
        ];
        //$response = $this->get('/auth/login');
        $response = $this->post('/auth/login', $data);
        $response->assertRedirect('/auth/login')
                ->assertSeeText('These credentials do not match our records');
    }
    
    public function testUserCantLoginWhenNotActivated()
    {
        
        $user = $this->createUser('secret', ['activated' => false]);

        $data = [
            'email' => $user->email,
            'password' => 'secret'                
        ];

        $response = $this->post('/auth/login', $data)
            ->assertRedirect('/auth/login')
            ->assertSeeText('Your account has not been activated yet. Check your spam folder for the activation email.');
    }
    
    public function testGuestCantAccessProtectedPages()
    {
       $response = $this->get('/dashboard')
            ->assertRedirect('/auth/login');

        // todo: expand?
    }

    public function testAuthenticatedUserCantAccessLoginAndRegisterPages()
    {
        $user = $this->createUser('secret');

        $this->actingAs($user)
            ->get('/auth/login')
            ->assertRedirect('/');

        $this->actingAs($user)
            ->get('/auth/register')
            ->assertRedirect('/');
    }
    
}
