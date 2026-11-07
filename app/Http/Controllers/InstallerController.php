<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CommunicationLog;
use App\Models\InventoryItem;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabReport;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Sample;
use App\Models\StockMovement;
use App\Models\SystemNotification;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PDO;
use Throwable;

class InstallerController extends Controller
{
    public static function isInstalled(): bool
    {
        try {
            if (!Schema::hasTable('users') || !Schema::hasTable('system_settings')) {
                return false;
            }
            return User::count() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function index()
    {
        $checks = $this->runEnvironmentChecks();
        $installed = self::isInstalled();
        $lockFile = storage_path('installed.lock');
        $lockMeta = file_exists($lockFile) ? json_decode((string) file_get_contents($lockFile), true) : null;

        return view('installer', [
            'checks' => $checks,
            'allPassed' => collect($checks['requirements'])->every(fn ($c) => $c['passed'])
                && collect($checks['permissions'])->every(fn ($c) => $c['passed']),
            'installed' => $installed,
            'lockMeta' => $lockMeta,
            'currentDbConnection' => config('database.default', 'sqlite'),
        ]);
    }

    public function testDatabase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'db_connection' => ['required', 'in:sqlite,mysql'],
            'db_host' => ['nullable', 'string'],
            'db_port' => ['nullable', 'numeric'],
            'db_database' => ['nullable', 'string'],
            'db_username' => ['nullable', 'string'],
            'db_password' => ['nullable', 'string'],
        ]);

        try {
            if ($validated['db_connection'] === 'sqlite') {
                if (!extension_loaded('pdo_sqlite')) {
                    return response()->json([
                        'connected' => false,
                        'message' => 'PHP pdo_sqlite extension is not enabled on this server.',
                    ], 422);
                }
                $dbPath = database_path('database.sqlite');
                if (!file_exists($dbPath)) {
                    @touch($dbPath);
                }
                new PDO('sqlite:' . $dbPath);

                return response()->json([
                    'connected' => true,
                    'message' => 'SQLite embedded database is ready and writable (' . basename($dbPath) . ').',
                ]);
            }

            // MySQL connection test
            $host = $validated['db_host'] ?: '127.0.0.1';
            $port = $validated['db_port'] ?: '3306';
            $dbName = trim((string) ($validated['db_database'] ?: 'labverse'));
            $user = $validated['db_username'] ?: 'root';
            $pass = $validated['db_password'] ?? '';

            $dsnWithoutDb = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsnWithoutDb, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            $safeDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            return response()->json([
                'connected' => true,
                'message' => "Connected to MySQL server ({$host}:{$port}) and verified database '{$safeDbName}'.",
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'connected' => false,
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function runInstall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Database
            'db_connection' => ['required', 'in:sqlite,mysql'],
            'db_host' => ['nullable', 'string', 'max:120'],
            'db_port' => ['nullable', 'numeric'],
            'db_database' => ['nullable', 'string', 'max:100'],
            'db_username' => ['nullable', 'string', 'max:100'],
            'db_password' => ['nullable', 'string', 'max:150'],

            // Facility & Branch
            'facility_name' => ['required', 'string', 'max:160'],
            'facility_accreditation' => ['nullable', 'string', 'max:160'],
            'branch_name' => ['required', 'string', 'max:150'],
            'branch_city' => ['required', 'string', 'max:100'],
            'branch_address' => ['required', 'string', 'max:255'],
            'branch_phone' => ['required', 'string', 'max:40'],

            // Administrator Account
            'admin_first_name' => ['required', 'string', 'max:80'],
            'admin_last_name' => ['required', 'string', 'max:80'],
            'admin_email' => ['required', 'email', 'max:150'],
            'admin_password' => ['required', 'string', 'min:8'],

            // Data Mode: 'demo' (with sample patients/orders) or 'production' (clean catalogue & roles only)
            'install_mode' => ['required', 'in:demo,production'],

            // Optional SMTP & SMS settings
            'smtp_driver' => ['nullable', 'in:log,smtp'],
            'smtp_host' => ['nullable', 'string', 'max:150'],
            'smtp_port' => ['nullable', 'numeric'],
            'smtp_encryption' => ['nullable', 'in:tls,ssl,none'],
            'smtp_username' => ['nullable', 'string', 'max:150'],
            'smtp_password' => ['nullable', 'string', 'max:150'],
            'smtp_from_address' => ['nullable', 'email', 'max:150'],

            'sms_provider' => ['nullable', 'in:sandbox,arkesel,mnotify,hubtel,termii,twilio,custom_webhook'],
            'sms_sender_id' => ['nullable', 'string', 'max:11'],
            'sms_api_key' => ['nullable', 'string', 'max:200'],
            'sms_api_secret' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            // 1. Update .env configuration
            $envUpdates = [
                'APP_NAME' => '"' . addslashes($validated['facility_name']) . '"',
                'DB_CONNECTION' => $validated['db_connection'],
            ];

            if ($validated['db_connection'] === 'sqlite') {
                $dbPath = database_path('database.sqlite');
                if (!file_exists($dbPath)) {
                    @touch($dbPath);
                }
                Config::set('database.default', 'sqlite');
                Config::set('database.connections.sqlite.database', $dbPath);
            } else {
                $host = $validated['db_host'] ?: '127.0.0.1';
                $port = (string) ($validated['db_port'] ?: '3306');
                $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $validated['db_database'] ?: 'labverse');
                $user = $validated['db_username'] ?: 'root';
                $pass = $validated['db_password'] ?? '';

                // Ensure database exists
                $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                $envUpdates['DB_HOST'] = $host;
                $envUpdates['DB_PORT'] = $port;
                $envUpdates['DB_DATABASE'] = $dbName;
                $envUpdates['DB_USERNAME'] = $user;
                $envUpdates['DB_PASSWORD'] = '"' . addslashes($pass) . '"';

                Config::set('database.default', 'mysql');
                Config::set('database.connections.mysql.host', $host);
                Config::set('database.connections.mysql.port', $port);
                Config::set('database.connections.mysql.database', $dbName);
                Config::set('database.connections.mysql.username', $user);
                Config::set('database.connections.mysql.password', $pass);
            }

            $this->writeEnvFile($envUpdates);
            DB::purge();

            // 2. Run database migrations & seed clinical catalogue
            Artisan::call('migrate:fresh', ['--force' => true]);
            $seeder = new DatabaseSeeder();
            $seeder->run();

            // 3. Customize Main Branch & Administrator Account
            $mainBranch = Branch::where('is_main', true)->first() ?? Branch::first();
            if ($mainBranch) {
                $mainBranch->update([
                    'name' => $validated['branch_name'],
                    'city' => $validated['branch_city'],
                    'region' => $validated['branch_city'],
                    'address' => $validated['branch_address'],
                    'phone' => $validated['branch_phone'],
                    'email' => $validated['admin_email'],
                    'manager_name' => trim($validated['admin_first_name'] . ' ' . $validated['admin_last_name']),
                ]);
            }

            $adminUser = User::where('is_platform_admin', true)->first() ?? User::first();
            if ($adminUser) {
                $adminUser->update([
                    'first_name' => $validated['admin_first_name'],
                    'last_name' => $validated['admin_last_name'],
                    'name' => trim($validated['admin_first_name'] . ' ' . $validated['admin_last_name']),
                    'email' => $validated['admin_email'],
                    'phone' => $validated['branch_phone'],
                    'password' => Hash::make($validated['admin_password']),
                ]);
            }

            // 4. If Clean Production Mode is chosen, clear demo patients/orders/samples and non-admin demo users
            if ($validated['install_mode'] === 'production') {
                CommunicationLog::query()->delete();
                StockMovement::query()->delete();
                InventoryItem::query()->delete();
                Payment::query()->delete();
                LabReport::query()->delete();
                LabOrderItem::query()->delete();
                Sample::query()->delete();
                LabOrder::query()->delete();
                Patient::query()->delete();
                SystemNotification::query()->delete();
                AuditLog::query()->delete();
                if ($adminUser) {
                    User::where('id', '!=', $adminUser->id)->delete();
                }
                if ($mainBranch) {
                    Branch::where('id', '!=', $mainBranch->id)->delete();
                }
            }

            // 5. Save Facility, SMTP & SMS settings
            $settingsToSave = [
                'facility_name' => $validated['facility_name'],
                'facility_accreditation' => $validated['facility_accreditation'] ?: 'ISO 15189 Accredited Clinical Laboratory',
                'facility_phone' => $validated['branch_phone'],
                'facility_email' => $validated['admin_email'],
                'smtp_enabled' => 'true',
                'smtp_driver' => $validated['smtp_driver'] ?? 'log',
                'smtp_host' => $validated['smtp_host'] ?? '',
                'smtp_port' => (string) ($validated['smtp_port'] ?? '587'),
                'smtp_encryption' => $validated['smtp_encryption'] ?? 'tls',
                'smtp_username' => $validated['smtp_username'] ?? '',
                'smtp_password' => $validated['smtp_password'] ?? '',
                'smtp_from_address' => $validated['smtp_from_address'] ?: $validated['admin_email'],
                'smtp_from_name' => $validated['facility_name'],
                'sms_enabled' => 'true',
                'sms_provider' => $validated['sms_provider'] ?? 'sandbox',
                'sms_sender_id' => strtoupper(substr($validated['sms_sender_id'] ?: 'LABVERSE', 0, 11)),
                'sms_api_key' => $validated['sms_api_key'] ?? '',
                'sms_api_secret' => $validated['sms_api_secret'] ?? '',
            ];

            foreach ($settingsToSave as $k => $v) {
                SystemSetting::updateOrCreate(
                    ['key' => $k],
                    ['value' => (string) $v, 'group' => str_starts_with($k, 'smtp_') ? 'smtp' : (str_starts_with($k, 'sms_') ? 'sms' : 'facility')]
                );
            }

            // 6. Write installation lock metadata
            $lockData = [
                'version' => '2.0.0',
                'installed_at' => now()->toIso8601String(),
                'facility_name' => $validated['facility_name'],
                'admin_email' => $validated['admin_email'],
                'install_mode' => $validated['install_mode'],
                'db_connection' => $validated['db_connection'],
            ];
            @file_put_contents(storage_path('installed.lock'), json_encode($lockData, JSON_PRETTY_PRINT));

            return response()->json([
                'installed' => true,
                'message' => 'LABVERSE v2.0 has been installed and configured on your server.',
                'admin_email' => $validated['admin_email'],
                'install_mode' => $validated['install_mode'],
                'redirect_url' => '/',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'installed' => false,
                'message' => 'Installation error: ' . $e->getMessage(),
            ], 422);
        }
    }

    protected function runEnvironmentChecks(): array
    {
        $requirements = [
            [
                'name' => 'PHP Runtime Version (>= 8.2)',
                'current' => PHP_VERSION,
                'passed' => version_compare(PHP_VERSION, '8.2.0', '>='),
            ],
            [
                'name' => 'PDO Database Extension',
                'current' => extension_loaded('pdo') ? 'Enabled' : 'Missing',
                'passed' => extension_loaded('pdo'),
            ],
            [
                'name' => 'SQLite or MySQL PDO Driver',
                'current' => (extension_loaded('pdo_sqlite') ? 'SQLite ' : '') . (extension_loaded('pdo_mysql') ? 'MySQL' : ''),
                'passed' => extension_loaded('pdo_sqlite') || extension_loaded('pdo_mysql'),
            ],
            [
                'name' => 'OpenSSL Encryption Extension',
                'current' => extension_loaded('openssl') ? 'Enabled' : 'Missing',
                'passed' => extension_loaded('openssl'),
            ],
            [
                'name' => 'Mbstring Unicode Extension',
                'current' => extension_loaded('mbstring') ? 'Enabled' : 'Missing',
                'passed' => extension_loaded('mbstring'),
            ],
            [
                'name' => 'cURL HTTP Extension (For SMS Gateways)',
                'current' => extension_loaded('curl') ? 'Enabled' : 'Missing',
                'passed' => extension_loaded('curl'),
            ],
            [
                'name' => 'JSON & Fileinfo Extensions',
                'current' => (extension_loaded('json') && extension_loaded('fileinfo')) ? 'Enabled' : 'Missing',
                'passed' => extension_loaded('json') && extension_loaded('fileinfo'),
            ],
        ];

        $permissions = [
            [
                'path' => 'storage/framework',
                'passed' => is_writable(storage_path('framework')),
            ],
            [
                'path' => 'storage/logs',
                'passed' => is_writable(storage_path('logs')),
            ],
            [
                'path' => 'bootstrap/cache',
                'passed' => is_writable(base_path('bootstrap/cache')),
            ],
            [
                'path' => 'database/',
                'passed' => is_writable(database_path()),
            ],
        ];

        return [
            'requirements' => $requirements,
            'permissions' => $permissions,
        ];
    }

    protected function writeEnvFile(array $values): void
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath) && file_exists(base_path('.env.example'))) {
            @copy(base_path('.env.example'), $envPath);
        }
        if (!file_exists($envPath) || !is_writable($envPath)) {
            return;
        }

        $content = (string) file_get_contents($envPath);
        foreach ($values as $key => $val) {
            $pattern = "/^{$key}=.*/m";
            if (preg_match($pattern, $content)) {
                $content = (string) preg_replace($pattern, "{$key}={$val}", $content);
            } else {
                $content .= "\n{$key}={$val}";
            }
        }
        @file_put_contents($envPath, $content);
    }
}

