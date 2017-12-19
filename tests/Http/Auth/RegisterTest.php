<?php

namespace OpenDominion\Tests\Http\Auth;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Notification;
use OpenDominion\Models\User;
use OpenDominion\Notifications\UserRegisteredNotification;
use OpenDominion\Tests\AbstractTestCase;

class RegisterTest extends AbstractTestCase
{
    use DatabaseMigrations;
    /*
        Test the get and post for auth/register
    */
    public function testUserCanRegister()
    {   
        $response = $this->get('/auth/register')
            ->assertSeeText('Register');
           
        $data = [
            'display_name' => 'John Doe',
            'email'=> 'johndoe@example.com',
            'password' => 'secret',
            'password_confirmation' => 'secret',
            'terms' => 'on'
        ];

        $response = $this->post('/auth/register',$data)
            ->assertSeeText('You have been successfully registered. An activation email has been dispatched to your address.');
        
        $user = User::where('email', 'johndoe@example.com')->firstOrFail();

        Notification::assertSentTo($user, UserRegisteredNotification::class);
    }
    
    public function testNewlyRegisteredUserCanActivateAccount()
    {
        $activation_code = str_random();
        $user = $this->createUser(null, [
            'activated' => false,
            'activation_code' => $activation_code,
        ]);
        $data = [
            'activation_code' => $activation_code
        ];
        $respsone = $this->post('/auth/activate', $data)
            ->assertRedirect('/dashboard')
            ->assertSeeText('Your account has been activated and you are now logged in.');

    }
    /*
    public function testUserCantActivateWithInvalidActivationCode()
    {
        $user = $this->createUser(null, [
            'activated' => false,
            'activation_code' => 'foo',
        ]);

        $this->visitRoute('auth.activate', 'bar')
            ->seeRouteIs('home')
            ->see('Invalid activation code')
            ->dontSeeInDatabase('users', [
                'id' => $user->id,
                'activated' => true,
            ]);
    }

    public function testUserCantRegisterWithBlankData()
    {
        $this->visitRoute('auth.register')
            ->see('Register')
            ->press('Register')
            ->seeRouteIs('auth.register')
            ->see('The display name field is required.')
            ->see('The email field is required.')
            ->see('The password field is required.');
    }

    public function testUserCantRegisterWithDuplicateEmail()
    {
        $this->createUser(null, ['email' => 'johndoe@example.com']);

        $this->visitRoute('auth.register')
            ->see('Register')
            ->type('John Doe', 'display_name')
            ->type('johndoe@example.com', 'email')
            ->type('password', 'password')
            ->type('password', 'password_confirmation')
            ->check('terms')
            ->press('Register')
            ->seeRouteIs('auth.register')
            ->see('The email has already been taken.');
    }

    public function testUserCantRegisterWithDuplicateDisplayName()
    {
        $this->createUser(null, ['display_name' => 'John Doe']);

        $this->visitRoute('auth.register')
            ->see('Register')
            ->type('John Doe', 'display_name')
            ->type('johndoe@example.com', 'email')
            ->type('password', 'password')
            ->type('password', 'password_confirmation')
            ->check('terms')
            ->press('Register')
            ->seeRouteIs('auth.register')
            ->see('The display name has already been taken.');
    }

    public function testUserCantRegisterWithNonMatchingPasswords()
    {
        $this->visitRoute('auth.register')
            ->see('Register')
            ->type('John Doe', 'display_name')
            ->type('johndoe@example.com', 'email')
            ->type('password1', 'password')
            ->type('password2', 'password_confirmation')
            ->check('terms')
            ->press('Register')
            ->seeRouteIs('auth.register')
            ->see('The password confirmation does not match.');
    }

    public function testUserCantRegisterWithoutAgreeingToTheTerms()
    {
        $this->visitRoute('auth.register')
            ->see('Register')
            ->type('John Doe', 'display_name')
            ->type('johndoe@example.com', 'email')
            ->type('password', 'password')
            ->type('password', 'password_confirmation')
            ->press('Register')
            ->seeRouteIs('auth.register')
            ->see('The terms field is required.');
    }
    */
}
