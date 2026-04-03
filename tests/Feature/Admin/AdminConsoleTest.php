<?php

use App\Http\Requests\UpdateMembershipRequest;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\MembershipChangeLog;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('non-admin users cannot access the admin console or admin api', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.users'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.memberships'))
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson(route('admin.overview'))
        ->assertForbidden();
});

test('admin users can view the admin page shell', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/index')
            ->where('auth.user.is_admin', true)
            ->where('isAdminArea', true)
            ->where('currentLedger', null)
            ->where('availableLedgers', [])
            ->etc()
        );
});

test('admin can access all admin pages', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.users'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/users/index'));

    $this->actingAs($admin)
        ->get(route('admin.memberships'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/memberships/index'));
});

test('UpdateMembershipRequest only authorizes admin users', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    $nonAdminRequest = new UpdateMembershipRequest;
    $nonAdminRequest->setUserResolver(fn () => $user);

    expect($nonAdminRequest->authorize())->toBeFalse();

    $adminRequest = new UpdateMembershipRequest;
    $adminRequest->setUserResolver(fn () => $admin);

    expect($adminRequest->authorize())->toBeTrue();
});

dataset('admin membership list routes', [
    'users index' => 'admin.users.index',
    'memberships index' => 'admin.memberships.index',
]);

test('admin membership list endpoints reuse eager loaded memberships', function (string $routeName) {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->count(3)->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $this->actingAs($admin)
            ->getJson(route($routeName))
            ->assertOk();
    } finally {
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();
    }

    $membershipSelectQueries = $queries
        ->filter(fn (array $query) => str_starts_with(strtolower($query['query']), 'select'))
        ->filter(fn (array $query) => str_contains($query['query'], 'user_memberships'))
        ->count();

    expect($membershipSelectQueries)->toBe(1);
})->with('admin membership list routes');

test('admin overview returns aggregate counts without ledger data', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $freeUser = User::factory()->create();
    $premiumUser = User::factory()->create();
    $earlierThisWeek = now()->copy()->startOfWeek();
    $expectedCreatedToday = $earlierThisWeek->isSameDay(now()) ? 3 : 2;

    $premiumUser->membership()->update([
        'tier' => 'premium',
        'status' => 'active',
    ]);

    $ledger = Ledger::factory()->for($premiumUser)->create([
        'name' => 'Private Household Ledger',
    ]);

    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = $ledger->categories()->first() ?? Category::factory()->for($ledger)->create();
    $payee = Payee::factory()->for($ledger)->create();

    // Create transactions: 2 today, 1 earlier this week.
    Transaction::factory()->for($ledger)->for($account)->for($category)->for($payee)->create([
        'created_at' => now(),
    ]);
    Transaction::factory()->for($ledger)->for($account)->for($category)->for($payee)->create([
        'created_at' => now(),
    ]);
    Transaction::factory()->for($ledger)->for($account)->for($category)->for($payee)->create([
        'created_at' => $earlierThisWeek,
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.overview'));

    $response->assertOk()
        ->assertJsonPath('users.total', 3)
        ->assertJsonPath('users.verified', 3)
        ->assertJsonPath('users.new_today', 3)
        ->assertJsonPath('memberships.by_tier.free', 2)
        ->assertJsonPath('memberships.by_tier.premium', 1)
        ->assertJsonPath('ledgers.total', 1)
        ->assertJsonPath('transactions.created_today', $expectedCreatedToday)
        ->assertJsonPath('transactions.created_this_week', 3)
        ->assertJsonMissing(['Private Household Ledger']);
});

test('admin can search the user list', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create([
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
    ]);
    User::factory()->create([
        'name' => 'Bob Jones',
        'email' => 'bob@example.com',
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.users.index', [
        'search' => 'alice',
    ]));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'alice@example.com')
        ->assertJsonPath('data.0.is_admin', false);
});

test('admin can list memberships with filters', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $freeUser = User::factory()->create([
        'name' => 'Free User',
        'email' => 'free@example.com',
    ]);
    $premiumUser = User::factory()->create([
        'name' => 'Premium Member',
        'email' => 'premium@example.com',
    ]);

    $premiumUser->membership()->update([
        'tier' => 'premium',
        'status' => 'trialing',
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.memberships.index', [
        'tier' => 'premium',
    ]));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.membership.tier', 'premium');
});

test('admin membership updates are persisted and audited', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create([
        'name' => 'Member To Upgrade',
    ]);

    $this->actingAs($admin)
        ->patchJson(route('admin.memberships.update', $user), [
            'tier' => 'premium',
            'status' => 'trialing',
            'reason' => 'Manual upgrade for launch cohort',
        ])
        ->assertOk()
        ->assertJsonPath('membership.tier', 'premium')
        ->assertJsonPath('membership.status', 'trialing');

    expect($user->refresh()->membership)
        ->tier->toBe('premium')
        ->status->toBe('trialing');

    expect(MembershipChangeLog::query()->where([
        'user_id' => $user->id,
        'changed_by_user_id' => $admin->id,
        'previous_tier' => 'free',
        'previous_status' => 'active',
        'new_tier' => 'premium',
        'new_status' => 'trialing',
    ])->exists())->toBeTrue();
});

test('new registrations create a default free active membership', function () {
    $this->skipUnlessFortifyFeature(Features::registration());

    $this->post(route('register.store'), [
        'name' => 'Membership Test User',
        'email' => 'membership-test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'membership-test@example.com')->firstOrFail();

    expect($user->membership)
        ->not->toBeNull()
        ->tier->toBe('free')
        ->status->toBe('active');
});

test('non-admin users cannot delete users from the admin api', function () {
    $user = User::factory()->create();
    $targetUser = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('admin.users.destroy', $targetUser))
        ->assertForbidden();
});

test('admin can delete a non-admin user and related auth artifacts', function () {
    Storage::fake('local');

    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create([
        'email' => 'delete-me@example.com',
        'password' => Hash::make('password'),
    ]);

    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = $ledger->categories()->first() ?? Category::factory()->for($ledger)->create();
    $payee = Payee::factory()->for($ledger)->create();
    $transaction = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->for($payee)
        ->create();

    $attachmentPath = "attachments/{$ledger->id}/admin-delete-test.pdf";

    Storage::disk('local')->put($attachmentPath, 'fake');

    $attachment = $transaction->attachments()->create([
        'filename' => 'admin-delete-test.pdf',
        'path' => $attachmentPath,
        'mime_type' => 'application/pdf',
        'size' => 4,
    ]);

    DB::table('sessions')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    $token = $user->createToken('Admin delete test');

    Notification::send($user, new class extends Illuminate\Notifications\Notification
    {
        public function via(object $notifiable): array
        {
            return ['database'];
        }

        public function toArray(object $notifiable): array
        {
            return ['message' => 'Delete me'];
        }
    });

    DB::table('password_reset_tokens')->insert([
        'email' => $user->email,
        'token' => 'reset-token',
        'created_at' => now(),
    ]);

    Storage::disk('local')->assertExists($attachment->path);

    $this->actingAs($admin)
        ->deleteJson(route('admin.users.destroy', $user))
        ->assertNoContent();

    $this->assertModelMissing($user);
    $this->assertDatabaseMissing('ledgers', ['id' => $ledger->id]);
    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    $this->assertDatabaseMissing('notifications', [
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
    ]);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    Storage::disk('local')->assertMissing($attachment->path);
});

test('admin delete only removes attachment files after the transaction commits', function () {
    Storage::fake('local');

    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = $ledger->categories()->first() ?? Category::factory()->for($ledger)->create();
    $payee = Payee::factory()->for($ledger)->create();
    $transaction = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->for($payee)
        ->create();

    $attachmentPath = "attachments/{$ledger->id}/admin-delete-rollback-test.pdf";

    Storage::disk('local')->put($attachmentPath, 'fake');

    $attachment = $transaction->attachments()->create([
        'filename' => 'admin-delete-rollback-test.pdf',
        'path' => $attachmentPath,
        'mime_type' => 'application/pdf',
        'size' => 4,
    ]);

    User::deleted(function (User $deletedUser) use ($user): void {
        if ($deletedUser->isNot($user)) {
            return;
        }

        throw new RuntimeException('Force rollback after user deletion.');
    });

    Storage::disk('local')->assertExists($attachment->path);

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($admin)->deleteJson(route('admin.users.destroy', $user)))
        ->toThrow(RuntimeException::class, 'Force rollback after user deletion.');

    $this->assertModelExists($user->fresh());
    $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
    Storage::disk('local')->assertExists($attachment->path);
});

test('admin delete logs a warning when attachment cleanup fails after commit', function () {
    $disk = Storage::fake('local');
    Log::spy();

    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    $ledger = Ledger::factory()->for($user)->create();
    $accountType = AccountType::factory()->for($ledger)->create();
    $account = Account::factory()->for($ledger)->for($accountType)->create();
    $category = $ledger->categories()->first() ?? Category::factory()->for($ledger)->create();
    $payee = Payee::factory()->for($ledger)->create();
    $transaction = Transaction::factory()
        ->for($ledger)
        ->for($account)
        ->for($category)
        ->for($payee)
        ->create();

    $attachmentPath = "attachments/{$ledger->id}/admin-delete-log-test.pdf";

    $disk->put($attachmentPath, 'fake');

    $attachment = $transaction->attachments()->create([
        'filename' => 'admin-delete-log-test.pdf',
        'path' => $attachmentPath,
        'mime_type' => 'application/pdf',
        'size' => 4,
    ]);

    Storage::shouldReceive('disk')
        ->once()
        ->with('local')
        ->andReturn(new class
        {
            /**
             * @param  array<int, string>  $paths
             */
            public function delete(array $paths): bool
            {
                throw new RuntimeException('Simulated attachment cleanup failure.');
            }
        });

    $this->actingAs($admin)
        ->deleteJson(route('admin.users.destroy', $user))
        ->assertNoContent();

    $this->assertModelMissing($user);
    $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    $disk->assertExists($attachment->path);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context) use ($attachment, $attachmentPath, $user): bool {
            return $message === 'Attachment cleanup failed after commit.'
                && $context['user_id'] === $user->id
                && $context['paths'] === [$attachmentPath]
                && $context['attachment_ids'] === [$attachment->id]
                && $context['exception'] instanceof RuntimeException
                && $context['exception']->getMessage() === 'Simulated attachment cleanup failure.';
        });
});

test('admin cannot delete another admin user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $targetAdmin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->deleteJson(route('admin.users.destroy', $targetAdmin))
        ->assertForbidden();

    $this->assertModelExists($targetAdmin);
});
