<?php
class User
{
    private $nickname;
    private $password;
    private $location;
    private $favoriteFood;
    private $active = false;
    private $activationKey;
    
    public function __construct($nickname, $activationKey = '')
    {
        $this->nickname = $nickname;
        $this->activationKey = $activationKey;
    }

    public function activate($key)
    {
        if ($this->activationKey === $key) {
            $this->active = true;
            return;
        }
        throw new InvalidArgumentException('Key for activation is incorrect.');
    }

    public function login($password, LoginAdapter $loginAdapter)
    {
        if ($this->password == $password) {
            $loginAdapter->storeIdentity($this->nickname);
            return true;
        } else {
            return false;
        }
    }

    public function handle($command)
    {
        if ($command instanceof ChangeUserPassword) {
            $this->handleChangeUserPassword($command);
        }
        if ($command instanceof SetUserDetails) {
            $this->handleSetUserDetails($command);
        }
        // support other commands here...
    }

    private function handleChangeUserPassword(ChangeUserPassword $command)
    {
        if ($command->getOldPassword() == $this->password) {
            $this->password = $command->getNewPassword();
        } else {
            throw new Exception('The old password is not correct.');
        }
    }

    private function handleSetUserDetails(SetUserDetails $command)
    {
        $this->location = $command->getLocation();
        $this->favoriteFood = $command->getFavoriteFood();
    }

    public function render(Canvas $canvas)
    {
        $canvas->nickname = $this->nickname;
        $canvas->location = $this->location;
        $canvas->favoriteFood = $this->favoriteFood;
    }

    /**
     * @deprecated: only needed for earlier tests
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @deprecated: only needed for earlier tests
     */
    public function getPassword()
    {
        return $this->password;
    }
}

class UserTest extends PHPUnit_Framework_TestCase
{
    // Constructor
    public function testPassWriteOnlyDataInTheConstructor()
    {
        $user = new User('giorgiosironi');
        // should not explode
        //removed the setter as it cannot be changed
    }

    // Information Expert
    /**
     * @expectedException InvalidArgumentException
     */
    public function testAnUserIsActivated()
    {
        $user = new User('giorgiosironi', 'ABC');
        $user->activate('AB');
    }

    // Double Dispatch
    public function testUsersLogin()
    {
        $user = new User('giorgiosironi');
        $user->setPassword('gs'); // will be removed in next tests
        // in reality, we would use a SessionLoginAdapter or something like that
        $loginAdapterMock = $this->getMock('LoginAdapter');
        $loginAdapterMock->expects($this->once())
                         ->method('storeIdentity')
                         ->with('giorgiosironi');

        $user->login('gs', $loginAdapterMock);
    }

    // Command/Changeset
    public function testCommandForChangingPassword()
    {
        $user = new User('giorgiosironi');
        $passwordChange = new ChangeUserPassword('', 'gs');
        $user->handle($passwordChange);
        $this->assertEquals('gs', $user->getPassword()); //deprecated, will be removed in next tests
    }

    // Canvas
    public function testCanvasForRenderingAnObject()
    {
        $user = new User('giorgiosironi');
        $detailsSet = new SetUserDetails('Italy', 'Pizza'); //THIS may have set/get
        $user->handle($detailsSet);
        // canvas can also be a form, or xml, or json...
        $canvas = new HtmlCanvas('<p>{{location}}</p><p>{{favoriteFood}}</p>');
        $user->render($canvas);
        $this->assertEquals('<p>Italy</p><p>Pizza</p>', (string) $canvas);
    }
    //or even CQRS
}


interface LoginAdapter
{
    /**
     * Saves identity somewhere.
     * @param string
     */
    public function storeIdentity($identity);
}

class ChangeUserPassword
{
    private $oldPassword;
    private $newPassword;

    /**
     * We may have logic for validating that the two field 'new password'
     * and 'repeat new password' the user has typed are equal.
     */
    public function __construct($oldPassword, $newPassword)
    {
        $this->oldPassword = $oldPassword;
        $this->newPassword = $newPassword;
    }

    public function getOldPassword()
    {
        return $this->oldPassword;
    }

    public function getNewPassword()
    {
        return $this->newPassword;
    }
}

/**
 * Another Changeset/Command.
 */
class SetUserDetails
{
    private $location;
    private $favoriteFood;

    public function __construct($location, $favoriteFood)
    {
        $this->location = $location;
        $this->favoriteFood = $favoriteFood;
    }

    public function getLocation()
    {
        return $this->location;
    }

    public function getFavoriteFood()
    {
        return $this->favoriteFood;
    }
}

/**
 * An Entity can render itself on any implementation of this.
 */
interface Canvas
{
    public function __set($fieldName, $value);
}

class HtmlCanvas implements Canvas
{
    private $template;
    private $values = array();

    public function __construct($template)
    {
        $this->template = $template;
    }

    public function __set($fieldName, $value)
    {
        $placeholder = '{{' . $fieldName . '}}';
        $this->values[$placeholder] = $value;
    }

    public function __toString()
    {
        return strtr($this->template, $this->values);
    }
}
