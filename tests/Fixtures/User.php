<?php

namespace Coderity\Wallet\Tests\Fixtures;

use Coderity\Wallet\Billable;
use Illuminate\Database\Eloquent\Model as Eloquent;

class User extends Eloquent
{
    use Billable;
}
