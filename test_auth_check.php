<?php

// Quick test to check if authorization works

use App\Data\Transactions\Input\GetTransactionIndexData;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;

// Create test users and ledgers
$user1 = User::factory()->create();
$user2 = User::factory()->create();
$ledger1 = Ledger::factory()->for($user1)->create();

// Create a mock request for user2 trying to access ledger1
$request = new Request;
$request->setUserResolver(fn () => $user2);
$request->setRouteResolver(fn () => new class
{
    public function parameter($key)
    {
        if ($key === 'ledger') {
            return app('db')->table('ledgers')->first();
        }
    }
});

// Test authorization
$auth = GetTransactionIndexData::authorize($request);
echo 'Authorization result: '.($auth ? 'ALLOWED' : 'DENIED').PHP_EOL;
