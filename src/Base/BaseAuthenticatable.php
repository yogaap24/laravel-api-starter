<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Base;

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Foundation\Auth\Access\Authorizable;

/**
 * Optional base for API user models (UUID PK + SoftDeletes + auth traits).
 *
 * Requires illuminate/auth + laravel/framework in the host app.
 * For Sanctum tokens, also use Laravel\Sanctum\HasApiTokens on the concrete User.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
abstract class BaseAuthenticatable extends BaseModel implements AuthorizableContract, AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable;
    use Authorizable;
    use CanResetPassword;
    use MustVerifyEmail;
}
