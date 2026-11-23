<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Data\PaymentRequestData;
use App\Data\PaymentResponseData;
use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Template;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Dashboard\AdminDashboardService;
use App\Services\Invitation\InvitationCreationService;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private AdminRecheckGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);

        config()->set('services.midtrans.server_key', 'test-server-key');
        config()->set('services.midtrans.client_key', 'test-client-key');

        $this->gateway = new AdminRecheckGateway();
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    public function test_dashboard_reports_all_prd_statistics_and_rejects_customers(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $admin = $this->admin();
        $first = $this->customer('admin-stats-1@example.test');
        $second = $this->customer('admin-stats-2@example.test');

        $this->invitation($first, InvitationStatus::Active, PaymentStatus::Settlement);
        $this->invitation($first, InvitationStatus::Active, PaymentStatus::Settlement);
        $this->invitation($first, InvitationStatus::Draft, PaymentStatus::Pending);
        $this->invitation($second, InvitationStatus::Preview, PaymentStatus::Pending);

        $this->transaction($first, $this->invitation($second, InvitationStatus::PendingPayment), 'UD-ADMIN-STAT-1', 150000, PaymentStatus::Settlement);
        $this->transaction($second, $this->invitation($second, InvitationStatus::Expired), 'UD-ADMIN-STAT-2', 200000, PaymentStatus::Paid);
        $this->transaction($second, $this->invitation($second, InvitationStatus::Expired), 'UD-ADMIN-STAT-3', 99000, PaymentStatus::Pending);

        ActivityLog::create([
            'user_id' => $admin->id,
            'action' => 'admin.dashboard.test',
            'description' => 'Aktivitas terbaru terlihat di dashboard admin.',
        ]);

        $summary = app(AdminDashboardService::class)->summary();

        $this->assertSame([
            'customers' => 2,
            'invitations' => 7,
            'active_invitations' => 2,
            'draft_invitations' => 2,
            'transactions' => 3,
            'revenue' => '350000.00',
            'pending_transactions' => 1,
            'templates' => Template::active()->count(),
        ], $summary['stats']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Total customer')
            ->assertSee('Total undangan')
            ->assertSee('Total undangan aktif')
            ->assertSee('Total draft')
            ->assertSee('Total transaksi')
            ->assertSee('Total pendapatan')
            ->assertSee('Rp350.000')
            ->assertSee('Transaksi pending')
            ->assertSee('Template tersedia')
            ->assertSee('Transaksi Terbaru')
            ->assertSee('UD-ADMIN-STAT-1')
            ->assertSee('Undangan Terbaru')
            ->assertSee('Aktivitas Terbaru')
            ->assertSee('Aktivitas terbaru terlihat di dashboard admin.');

        $this->actingAs($first)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_can_review_customers_and_deactivate_reactivate_them_with_audit_trail(): void
    {
        $admin = $this->admin();
        $customer = $this->customer('admin-customer@example.test', 'Customer Admin');
        $invitation = $this->invitation($customer, InvitationStatus::Active, PaymentStatus::Settlement);
        $this->transaction($customer, $invitation, 'UD-ADMIN-CUSTOMER', 150000, PaymentStatus::Settlement);

        $this->actingAs($admin)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Customer Admin')
            ->assertSee('admin-customer@example.test')
            ->assertDontSee($admin->email);

        $this->actingAs($admin)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee($invitation->title)
            ->assertSee('UD-ADMIN-CUSTOMER')
            ->assertSee('Nonaktifkan Akun');

        $this->actingAs($admin)
            ->post(route('admin.customers.status', $customer), [
                'status' => 'inactive',
                'reason' => 'Permintaan penghapusan akun',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Status akun customer berhasil diperbarui.');

        $this->assertSame(UserStatus::Inactive, $customer->fresh()->status);

        $log = ActivityLog::where('subject_type', 'customer')
            ->where('subject_id', $customer->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('customer.deactivated_by_admin', $log->action);
        $this->assertSame('Permintaan penghapusan akun', $log->reason);
        $this->assertSame($admin->id, $log->user_id);

        $this->actingAs($customer->fresh())->get(route('dashboard'))->assertRedirect(route('login'));

        $this->actingAs($admin)
            ->post(route('admin.customers.status', $customer), ['status' => 'active'])
            ->assertRedirect();

        $this->assertSame(UserStatus::Active, $customer->fresh()->status);
        $this->assertSame(2, ActivityLog::where('subject_type', 'customer')->where('subject_id', $customer->id)->count());

        $this->actingAs($admin)
            ->get(route('admin.customers.show', $admin))
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('admin.customers.status', $admin), ['status' => 'inactive'])
            ->assertForbidden();
    }

    public function test_admin_can_manually_activate_and_suspend_invitation_with_reason_and_audit(): void
    {
        $admin = $this->admin();
        $customer = $this->customer('admin-invitation@example.test');
        $invitation = $this->invitation($customer, InvitationStatus::PendingPayment, PaymentStatus::Pending);
        $invitation->groom()->create(['full_name' => 'Andi Pratama']);
        $invitation->bride()->create(['full_name' => 'Siti Rahma']);

        $publicUrl = route('invitation.show', $invitation->slug, false);
        $this->get($publicUrl)->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.invitations.index'))
            ->assertOk()
            ->assertSee($invitation->title)
            ->assertSee('Menunggu Pembayaran');

        $this->actingAs($admin)
            ->get(route('admin.invitations.show', $invitation))
            ->assertOk()
            ->assertSee('Andi Pratama & Siti Rahma')
            ->assertSee('Ubah Status Manual');

        $this->actingAs($admin)
            ->post(route('admin.invitations.status', $invitation), [
                'status' => 'ACTIVE',
                'reason' => 'ab',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(InvitationStatus::PendingPayment, $invitation->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.invitations.status', $invitation), [
                'status' => 'ACTIVE',
                'reason' => 'Pembayaran dikonfirmasi manual oleh admin',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Status undangan berhasil diperbarui.');

        $fresh = $invitation->fresh();
        $this->assertSame(InvitationStatus::Active, $fresh->status);
        $this->assertSame(PaymentStatus::Pending, $fresh->payment_status);
        $this->assertNotNull($fresh->activated_at);
        $this->assertNotNull($fresh->published_at);
        $this->get($publicUrl)->assertOk();

        $activationLog = ActivityLog::where('action', 'invitation.activated_by_admin')->firstOrFail();
        $this->assertSame('invitation', $activationLog->subject_type);
        $this->assertSame($invitation->id, $activationLog->subject_id);
        $this->assertSame('Pembayaran dikonfirmasi manual oleh admin', $activationLog->reason);

        $this->actingAs($admin)
            ->get(route('admin.invitations.show', $invitation))
            ->assertOk()
            ->assertSee('Pembayaran dikonfirmasi manual oleh admin');

        $this->actingAs($admin)
            ->post(route('admin.invitations.status', $invitation), [
                'status' => 'SUSPENDED',
                'reason' => 'Laporan duplikasi undangan',
            ])
            ->assertRedirect();

        $this->assertSame(InvitationStatus::Suspended, $invitation->fresh()->status);
        $this->get($publicUrl)->assertNotFound();
        $this->assertSame(4, ActivityLog::where('subject_type', 'invitation')->where('subject_id', $invitation->id)->count());
    }

    public function test_admin_transaction_recheck_uses_verified_provider_status_and_is_idempotent(): void
    {
        $admin = $this->admin();
        $customer = $this->customer('admin-recheck@example.test');
        $invitation = $this->invitation($customer, InvitationStatus::PendingPayment, PaymentStatus::Pending);
        $transaction = $this->transaction($customer, $invitation, 'UD-ADMIN-RECHECK', 150000, PaymentStatus::Pending);

        $this->gateway->statusResponses[$transaction->order_id] = [
            'gross_amount' => 150000,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'transaction_time' => now()->toIso8601String(),
            'transaction_id' => 'midtrans-admin-recheck',
            'payment_type' => 'qris',
        ];

        $this->actingAs($admin)
            ->get(route('admin.transactions.index'))
            ->assertOk()
            ->assertSee('UD-ADMIN-RECHECK');

        $this->actingAs($admin)
            ->get(route('admin.transactions.show', $transaction))
            ->assertOk()
            ->assertSee('Periksa Ulang ke Midtrans');

        $this->actingAs($admin)
            ->post(route('admin.transactions.check', $transaction))
            ->assertRedirect()
            ->assertSessionHas('status', 'Pembayaran terverifikasi dan undangan aktif.');

        $this->assertSame(PaymentStatus::Settlement, $transaction->fresh()->transaction_status);
        $this->assertSame(InvitationStatus::Active, $invitation->fresh()->status);
        $this->assertSame(PaymentStatus::Settlement, $invitation->fresh()->payment_status);
        $this->get(route('invitation.show', $invitation->slug, false))->assertOk();
        $this->assertSame(1, ActivityLog::where('action', 'invitation.activated')->count());
        $this->assertSame(1, ActivityLog::where('action', 'transaction.rechecked_by_admin')->count());

        $this->actingAs($admin)
            ->post(route('admin.transactions.check', $transaction))
            ->assertRedirect()
            ->assertSessionHas('status', 'Status transaksi diperbarui: SETTLEMENT.');

        $this->assertSame(1, ActivityLog::where('action', 'invitation.activated')->count());
        $this->assertSame(0, (int) $transaction->fresh()->notification_count);
        $this->assertSame(2, ActivityLog::where('action', 'transaction.rechecked_by_admin')->count());

        $this->gateway->statusResponses[$transaction->order_id]['gross_amount'] = 999999;

        $this->actingAs($admin)
            ->post(route('admin.transactions.check', $transaction))
            ->assertRedirect()
            ->assertSessionHas('error', 'Nominal pembayaran tidak sesuai.');

        $this->assertSame(InvitationStatus::Active, $invitation->fresh()->status);
        $this->assertSame(2, ActivityLog::where('action', 'transaction.rechecked_by_admin')->count());
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Super Admin',
            'email' => 'admin-slice@example.test',
            'google_id' => 'google-admin-slice',
            'role' => Role::SuperAdmin,
            'status' => UserStatus::Active,
        ]);
    }

    private function customer(string $email, string $name = 'Customer'): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'google_id' => 'google-' . $email,
            'role' => Role::Customer,
            'status' => UserStatus::Active,
        ]);
    }

    private function invitation(
        User $customer,
        InvitationStatus $status = InvitationStatus::Draft,
        PaymentStatus $paymentStatus = PaymentStatus::Pending,
    ): Invitation {
        $invitation = app(InvitationCreationService::class)
            ->create(Template::firstWhere('slug', 'tema1'), $customer);

        $invitation->update(['status' => $status, 'payment_status' => $paymentStatus]);

        return $invitation;
    }

    private function transaction(
        User $customer,
        Invitation $invitation,
        string $orderId,
        int $amount,
        PaymentStatus $status,
    ): Transaction {
        return Transaction::create([
            'user_id' => $customer->id,
            'invitation_id' => $invitation->id,
            'order_id' => $orderId,
            'gross_amount' => $amount,
            'payment_type' => 'qris',
            'transaction_status' => $status,
            'transaction_time' => now(),
        ]);
    }
}

class AdminRecheckGateway implements PaymentGateway
{
    /** @var array<string, array<string, mixed>> */
    public array $statusResponses = [];

    public function createTransaction(PaymentRequestData $request): PaymentResponseData
    {
        return new PaymentResponseData(
            token: 'token-' . $request->orderId,
            redirectUrl: 'https://midtrans.test/' . $request->orderId,
            raw: [],
        );
    }

    public function fetchStatus(string $orderId): array
    {
        return $this->statusResponses[$orderId] ?? [
            'order_id' => $orderId,
            'gross_amount' => 150000,
            'transaction_status' => 'pending',
        ];
    }

    public function cancel(string $orderId): void
    {
        // No external call in the fake gateway.
    }
}
