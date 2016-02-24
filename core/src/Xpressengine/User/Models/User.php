<?php
/**
 * User
 *
 * PHP version 5
 *
 * @category    User
 * @package     Xpressengine\User
 * @author      XE Team (developers) <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/lgpl-3.0-standalone.html LGPL
 * @link        http://www.xpressengine.com
 */
namespace Xpressengine\User\Models;

use Closure;
use Xpressengine\Database\Eloquent\DynamicModel;
use Xpressengine\User\Rating;
use Xpressengine\User\UserInterface;

/**
 * @category    User
 * @package     Xpressengine\User
 * @author      XE Team (developers) <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/lgpl-3.0-standalone.html LGPL
 * @link        http://www.xpressengine.com
 */
class User extends DynamicModel implements UserInterface
{

    protected $table = 'user';

    public $incrementing = false;

    /**
     * @var bool use dynamic query
     */
    protected $dynamic = false;

    protected $dates = [
        'passwordUpdatedAt'
    ];

    protected $hidden = ['password'];

    /**
     * @var \Closure 회원의 프로필 이미지 Resolver. 프로필 이미지 아이디에 해당하는 프로필 이미지 URL을 반환한다.
     */
    protected static $profileImageResolver;

    /**
     * @var string 비밀번호 초기화 요청을 처리할 때, 입력된 이메일을 저장함
     */
    protected $emailForPasswordReset;

    /**
     * @var string getDisplayName()메소드가 실행될 때 사용할 필드
     */
    public static $displayField = 'displayName';

    /**
     * setProfileImageResolver
     *
     * @param Closure $callback 회원의 프로필 이미지를 처리하기 위한 resolver
     *
     * @return void
     */
    public static function setProfileImageResolver(Closure $callback)
    {
        static::$profileImageResolver = $callback;
    }


    /**
     * set relationship with user groups
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function groups()
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_user', 'userId', 'groupId');
    }

    /**
     * set relationship with user accounts
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function accounts()
    {
        return $this->hasMany(UserAccount::class, 'userId');
    }

    /**
     * set relationship with emails
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function emails()
    {
        return $this->hasMany(UserEmail::class, 'userId');
    }

    /**
     * set relationship with pendingEmails
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function pendingEmails()
    {
        return $this->hasOne(PendingEmail::class, 'userId');
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->getAttribute('id');
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->getAttribute('password');
    }

    /**
     * Get the token value for the "remember me" session.
     *
     * @return string
     */
    public function getRememberToken()
    {
        return $this->getAttribute($this->getRememberTokenName());
    }

    /**
     * setEmailForPasswordReset() 메소드에서 반환할 email 정보를 지정한다.
     *
     * @param string $email 지정할 email주소
     *
     * @return void
     */
    public function setEmailForPasswordReset($email)
    {
        $this->emailForPasswordReset = $email;
    }

    /**
     * Set the token value for the "remember me" session.
     *
     * @param  string $value
     *
     * @return void
     */
    public function setRememberToken($value)
    {
        $this->setAttribute($this->getRememberTokenName(), $value);
    }

    /**
     * Get the column name for the "remember me" token.
     *
     * @return string
     */
    public function getRememberTokenName()
    {
        return 'rememberToken';
    }

    /**
     * Get the e-mail address where password reset links are sent.
     *
     * @return string
     */
    public function getEmailForPasswordReset()
    {
        // 만약 로그인시 사용된 이메일이 따로 지정돼 있을 경우 그 이메일을 사용한다.
        return isset($this->emailForPasswordReset) ? $this->emailForPasswordReset : $this->email;
    }

    /**
     * Get the unique identifier
     *
     * @return string
     */
    public function getId()
    {
        return $this->getAttribute('id');
    }

    /**
     * Get the name for display
     *
     * @return string
     */
    public function getDisplayName()
    {
        $field = static::$displayField;
        return $this->getAttribute($field);
    }

    /**
     * Get the rating of user
     *
     * @return string
     */
    public function getRating()
    {
        return $this->getAttribute('rating');
    }

    /**
     * Finds whether user has super rating.
     *
     * @return boolean
     */
    public function isAdmin()
    {
        return $this->getRating() === Rating::SUPER;
    }

    /**
     * Finds whether user has manager or super rating.
     *
     * @return boolean
     */
    public function isManager()
    {
        return Rating::compare($this->getRating(), Rating::MANAGER) >= 0;
    }

    /**
     * Get the status of user
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->getAttribute('status');
    }

    /**
     * Get profile image URL of user
     *
     * @return string
     */
    public function getProfileImage()
    {
        $resolver = static::$profileImageResolver;
        return $resolver($this->profileImageId);
    }

    /**
     * Get groups a user belongs
     *
     * @return array
     */
    public function getGroups()
    {
        return $this->getAttribute('groups') ?: [];
    }

    /**
     * Get Pending Email of current user
     *
     * @return PendingEmail
     */
    public function getPendingEmail()
    {
        $mails = $this->getAttribute('pending_mails');
        if ($mails === null) {
            return null;
        }
        if (is_array($mails) && !empty($mails)) {
            return reset($mails);
        }
    }

    /**
     * 회원이 소유한 계정 중에 주어진 provider를 가진 계정을 반환한다.
     *
     * @param string $provider provider
     *
     * @return UserAccount
     */
    public function getAccountByProvider($provider)
    {
        foreach ($this->$this->getAttribute('accounts') as $account) {
            if ($account->provider === $provider) {
                return $account;
            }
        }

        return null;
    }

    /**
     * add this user to groups
     *
     * @param array $groups
     *
     * @return static
     */
    public function joinGroups(array $groups)
    {
        $this->groups()->attach($groups);
        return $this;
    }

    /**
     * leave groups
     *
     * @param array $groups
     *
     * @return static
     */
    public function leaveGroups(array $groups)
    {
        $this->groups()->detach($groups);
        return $this;
    }
}