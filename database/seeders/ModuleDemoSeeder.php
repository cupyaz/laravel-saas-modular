<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\ModuleVersion;
use App\Models\ModuleInstallation;
use App\Models\ModuleReview;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class ModuleDemoSeeder extends Seeder
{
    /**
     * Run the database seeds for module demo data.
     */
    public function run(): void
    {
        $this->command->info('🧩 Creating demo modules for Laravel SaaS Platform...');

        // Create demo modules
        $modules = $this->createModules();
        
        // Create module versions
        $this->createModuleVersions($modules);
        
        // Create some installations
        $this->createModuleInstallations($modules);
        
        // Create reviews
        $this->createModuleReviews($modules);

        $this->command->info('✅ Module demo data created successfully!');
        $this->printModuleCredentials($modules);
    }

    private function createModules(): array
    {
        $this->command->info('📦 Creating demo modules...');

        $modulesData = [
            [
                'name' => 'Advanced Analytics Dashboard',
                'slug' => 'advanced-analytics',
                'description' => 'Comprehensive analytics and reporting module with real-time data visualization, custom dashboards, and automated report generation.',
                'version' => '2.1.4',
                'author' => 'DataViz Solutions',
                'category' => 'analytics',
                'license' => 'MIT',
                'repository_url' => 'https://github.com/dataviz/advanced-analytics',
                'documentation_url' => 'https://docs.dataviz.com/analytics',
                'icon' => 'https://cdn.example.com/icons/analytics.svg',
                'screenshots' => [
                    'https://cdn.example.com/screenshots/analytics-1.jpg',
                    'https://cdn.example.com/screenshots/analytics-2.jpg',
                    'https://cdn.example.com/screenshots/analytics-3.jpg'
                ],
                'requirements' => [
                    'php' => '8.1',
                    'laravel' => '10.0',
                    'extensions' => ['gd', 'zip', 'curl']
                ],
                'config_schema' => [
                    'refresh_interval' => [
                        'type' => 'integer',
                        'required' => true,
                        'min' => 5,
                        'max' => 300,
                        'description' => 'Data refresh interval in seconds'
                    ],
                    'chart_theme' => [
                        'type' => 'string',
                        'required' => false,
                        'options' => ['light', 'dark', 'auto'],
                        'description' => 'Default chart theme'
                    ]
                ],
                'default_config' => [
                    'refresh_interval' => 30,
                    'chart_theme' => 'auto'
                ],
                'is_featured' => true,
                'price' => 4999, // €49.99
                'rating' => 4.8,
                'download_count' => 15420
            ],
            [
                'name' => 'Multi-Factor Authentication Pro',
                'slug' => 'mfa-pro',
                'description' => 'Enterprise-grade multi-factor authentication with support for TOTP, SMS, email, and hardware tokens. Includes advanced security features and compliance tools.',
                'version' => '1.5.2',
                'author' => 'SecureAuth Systems',
                'category' => 'authentication',
                'license' => 'Commercial',
                'repository_url' => 'https://github.com/secureauth/mfa-pro',
                'documentation_url' => 'https://docs.secureauth.com/mfa-pro',
                'icon' => 'https://cdn.example.com/icons/mfa.svg',
                'screenshots' => [
                    'https://cdn.example.com/screenshots/mfa-1.jpg',
                    'https://cdn.example.com/screenshots/mfa-2.jpg'
                ],
                'requirements' => [
                    'php' => '8.2',
                    'extensions' => ['openssl', 'curl', 'mbstring']
                ],
                'config_schema' => [
                    'totp_window' => [
                        'type' => 'integer',
                        'required' => true,
                        'min' => 1,
                        'max' => 10,
                        'description' => 'TOTP time window tolerance'
                    ],
                    'backup_codes_count' => [
                        'type' => 'integer',
                        'required' => false,
                        'min' => 5,
                        'max' => 20,
                        'description' => 'Number of backup codes to generate'
                    ]
                ],
                'default_config' => [
                    'totp_window' => 2,
                    'backup_codes_count' => 10
                ],
                'is_featured' => true,
                'price' => 9999, // €99.99
                'rating' => 4.9,
                'download_count' => 8732
            ],
            [
                'name' => 'Payment Gateway Integrator',
                'slug' => 'payment-integrator',
                'description' => 'Universal payment gateway integration module supporting Stripe, PayPal, Square, and 15+ other providers with unified API.',
                'version' => '3.2.1',
                'author' => 'PayFlow Technologies',
                'category' => 'payment',
                'license' => 'GPL-3.0',
                'repository_url' => 'https://github.com/payflow/payment-integrator',
                'documentation_url' => 'https://docs.payflow.com/integrator',
                'icon' => 'https://cdn.example.com/icons/payment.svg',
                'requirements' => [
                    'php' => '8.1',
                    'extensions' => ['curl', 'json', 'openssl']
                ],
                'config_schema' => [
                    'default_currency' => [
                        'type' => 'string',
                        'required' => true,
                        'options' => ['USD', 'EUR', 'GBP', 'JPY'],
                        'description' => 'Default payment currency'
                    ],
                    'webhook_retries' => [
                        'type' => 'integer',
                        'required' => false,
                        'min' => 1,
                        'max' => 5,
                        'description' => 'Webhook retry attempts'
                    ]
                ],
                'default_config' => [
                    'default_currency' => 'EUR',
                    'webhook_retries' => 3
                ],
                'price' => 7999, // €79.99
                'rating' => 4.6,
                'download_count' => 12150
            ],
            [
                'name' => 'Advanced Notification Center',
                'slug' => 'notification-center',
                'description' => 'Comprehensive notification system with email templates, SMS, push notifications, and in-app notifications with rich customization.',
                'version' => '1.8.3',
                'author' => 'NotifyHub',
                'category' => 'communication',
                'license' => 'MIT',
                'repository_url' => 'https://github.com/notifyhub/notification-center',
                'documentation_url' => 'https://docs.notifyhub.com/center',
                'icon' => 'https://cdn.example.com/icons/notifications.svg',
                'requirements' => [
                    'php' => '8.0',
                    'extensions' => ['curl', 'json']
                ],
                'config_schema' => [
                    'email_provider' => [
                        'type' => 'string',
                        'required' => true,
                        'options' => ['mailgun', 'sendgrid', 'ses', 'smtp'],
                        'description' => 'Email service provider'
                    ]
                ],
                'default_config' => [
                    'email_provider' => 'smtp'
                ],
                'is_featured' => true,
                'price' => 2999, // €29.99
                'rating' => 4.7,
                'download_count' => 23870
            ],
            [
                'name' => 'Content Management Studio',
                'slug' => 'cms-studio',
                'description' => 'Full-featured content management system with drag-and-drop page builder, media library, and SEO optimization tools.',
                'version' => '2.5.0',
                'author' => 'WebBuilder Co.',
                'category' => 'content',
                'license' => 'Commercial',
                'repository_url' => 'https://github.com/webbuilder/cms-studio',
                'documentation_url' => 'https://docs.webbuilder.com/cms',
                'icon' => 'https://cdn.example.com/icons/cms.svg',
                'requirements' => [
                    'php' => '8.1',
                    'extensions' => ['gd', 'imagick', 'zip']
                ],
                'config_schema' => [
                    'max_upload_size' => [
                        'type' => 'integer',
                        'required' => false,
                        'min' => 1,
                        'max' => 100,
                        'description' => 'Max file upload size in MB'
                    ]
                ],
                'default_config' => [
                    'max_upload_size' => 10
                ],
                'price' => 12999, // €129.99
                'rating' => 4.5,
                'download_count' => 7290
            ],
            [
                'name' => 'API Rate Limiter Pro',
                'slug' => 'rate-limiter-pro',
                'description' => 'Advanced rate limiting solution with Redis clustering, custom throttling rules, and detailed analytics.',
                'version' => '1.3.1',
                'author' => 'API Tools Ltd',
                'category' => 'utility',
                'license' => 'MIT',
                'repository_url' => 'https://github.com/apitools/rate-limiter-pro',
                'documentation_url' => 'https://docs.apitools.com/rate-limiter',
                'icon' => 'https://cdn.example.com/icons/rate-limiter.svg',
                'requirements' => [
                    'php' => '8.1',
                    'extensions' => ['redis']
                ],
                'config_schema' => [
                    'redis_cluster' => [
                        'type' => 'boolean',
                        'required' => false,
                        'description' => 'Enable Redis cluster support'
                    ]
                ],
                'default_config' => [
                    'redis_cluster' => false
                ],
                'price' => 0, // Free
                'rating' => 4.4,
                'download_count' => 34521
            ],
            [
                'name' => 'E-commerce Integration Suite',
                'slug' => 'ecommerce-suite',
                'description' => 'Complete e-commerce solution with shopping cart, inventory management, order processing, and multi-currency support.',
                'version' => '4.1.2',
                'author' => 'Commerce Solutions Inc',
                'category' => 'ecommerce',
                'license' => 'Commercial',
                'repository_url' => 'https://github.com/commercesolutions/ecommerce-suite',
                'documentation_url' => 'https://docs.commercesolutions.com/suite',
                'icon' => 'https://cdn.example.com/icons/ecommerce.svg',
                'requirements' => [
                    'php' => '8.2',
                    'extensions' => ['bcmath', 'curl', 'gd']
                ],
                'config_schema' => [
                    'tax_calculation' => [
                        'type' => 'string',
                        'required' => true,
                        'options' => ['none', 'simple', 'advanced'],
                        'description' => 'Tax calculation method'
                    ]
                ],
                'default_config' => [
                    'tax_calculation' => 'simple'
                ],
                'is_featured' => true,
                'price' => 19999, // €199.99
                'rating' => 4.8,
                'download_count' => 4567
            ],
            [
                'name' => 'Task Automation Engine',
                'slug' => 'automation-engine',
                'description' => 'Powerful workflow automation tool with visual editor, conditional logic, and integration with popular services.',
                'version' => '1.7.4',
                'author' => 'AutoFlow Systems',
                'category' => 'workflow',
                'license' => 'GPL-3.0',
                'repository_url' => 'https://github.com/autoflow/automation-engine',
                'documentation_url' => 'https://docs.autoflow.com/engine',
                'icon' => 'https://cdn.example.com/icons/automation.svg',
                'requirements' => [
                    'php' => '8.1',
                    'extensions' => ['curl', 'json', 'zip']
                ],
                'config_schema' => [
                    'max_concurrent_tasks' => [
                        'type' => 'integer',
                        'required' => false,
                        'min' => 1,
                        'max' => 50,
                        'description' => 'Maximum concurrent automation tasks'
                    ]
                ],
                'default_config' => [
                    'max_concurrent_tasks' => 10
                ],
                'price' => 5999, // €59.99
                'rating' => 4.3,
                'download_count' => 9876
            ]
        ];

        $modules = [];
        foreach ($modulesData as $moduleData) {
            $module = Module::updateOrCreate(
                ['slug' => $moduleData['slug']],
                $moduleData
            );
            $modules[] = $module;
        }

        return $modules;
    }

    private function createModuleVersions(array $modules): void
    {
        $this->command->info('🏷️ Creating module versions...');

        foreach ($modules as $module) {
            // Current version (already set in module)
            $currentVersion = ModuleVersion::updateOrCreate([
                'module_id' => $module->id,
                'version' => $module->version
            ], [
                'title' => 'Latest Stable Release',
                'description' => 'Current stable version with latest features and bug fixes',
                'changelog' => [
                    'features' => ['New dashboard layout', 'Improved performance', 'Enhanced security'],
                    'fixes' => ['Fixed memory leak issue', 'Resolved compatibility problems'],
                    'security' => ['Updated dependencies', 'Security patches applied']
                ],
                'is_stable' => true,
                'published_at' => now()->subDays(rand(1, 30)),
                'file_size' => rand(1024000, 10240000), // 1MB to 10MB
                'file_hash' => 'sha256:' . hash('sha256', $module->slug . $module->version),
                'installation_success_rate' => rand(85, 99) / 100,
                'download_count' => rand(100, 5000)
            ]);

            // Previous stable version
            $prevVersion = $this->decrementVersion($module->version);
            ModuleVersion::updateOrCreate([
                'module_id' => $module->id,
                'version' => $prevVersion
            ], [
                'title' => 'Previous Stable',
                'description' => 'Previous stable release',
                'changelog' => [
                    'features' => ['Initial features', 'Basic functionality'],
                    'fixes' => ['Minor bug fixes']
                ],
                'is_stable' => true,
                'published_at' => now()->subDays(rand(30, 90)),
                'file_size' => rand(1024000, 8192000),
                'file_hash' => 'sha256:' . hash('sha256', $module->slug . $prevVersion),
                'installation_success_rate' => rand(80, 95) / 100,
                'download_count' => rand(500, 3000)
            ]);

            // Beta version (next version)
            $nextVersion = $this->incrementVersion($module->version, 'minor');
            ModuleVersion::updateOrCreate([
                'module_id' => $module->id,
                'version' => $nextVersion
            ], [
                'title' => 'Beta Release',
                'description' => 'Beta version with experimental features',
                'changelog' => [
                    'features' => ['New experimental features', 'UI improvements'],
                    'notes' => ['This is a beta release - use with caution']
                ],
                'is_beta' => true,
                'published_at' => now()->subDays(rand(1, 14)),
                'file_size' => rand(1024000, 12288000),
                'file_hash' => 'sha256:' . hash('sha256', $module->slug . $nextVersion),
                'installation_success_rate' => rand(70, 85) / 100,
                'download_count' => rand(50, 500)
            ]);
        }
    }

    private function createModuleInstallations(array $modules): void
    {
        $this->command->info('💿 Creating module installations...');

        $tenants = Tenant::all();
        
        foreach ($tenants as $tenant) {
            // Install 3-5 random modules per tenant
            $installCount = rand(3, 5);
            $selectedModules = collect($modules)->random($installCount);

            foreach ($selectedModules as $module) {
                $status = collect([
                    ModuleInstallation::STATUS_ACTIVE,
                    ModuleInstallation::STATUS_ACTIVE,
                    ModuleInstallation::STATUS_ACTIVE,
                    ModuleInstallation::STATUS_INACTIVE,
                    ModuleInstallation::STATUS_ERROR
                ])->random();

                $installation = ModuleInstallation::updateOrCreate([
                    'module_id' => $module->id,
                    'tenant_id' => $tenant->id
                ], [
                    'version' => $module->version,
                    'status' => $status,
                    'config' => array_merge($module->getDefaultConfig(), [
                        'custom_setting' => 'value_' . $tenant->id,
                        'enabled' => true
                    ]),
                    'installed_at' => now()->subDays(rand(1, 90)),
                    'activated_at' => $status === ModuleInstallation::STATUS_ACTIVE ? now()->subDays(rand(1, 89)) : null,
                    'auto_update' => rand(0, 1),
                    'installation_method' => collect([
                        ModuleInstallation::METHOD_API,
                        ModuleInstallation::METHOD_MARKETPLACE,
                        ModuleInstallation::METHOD_MANUAL
                    ])->random(),
                    'installation_source' => 'marketplace'
                ]);

                // Add some performance data for active installations
                if ($status === ModuleInstallation::STATUS_ACTIVE) {
                    $performanceData = [];
                    for ($i = 0; $i < 10; $i++) {
                        $performanceData[] = [
                            'timestamp' => now()->subDays($i)->toISOString(),
                            'metrics' => [
                                'response_time' => rand(100, 800),
                                'memory_usage' => rand(10, 50),
                                'cpu_usage' => rand(5, 25)
                            ]
                        ];
                    }
                    $installation->update(['performance_data' => $performanceData]);
                }

                // Add error log for error status
                if ($status === ModuleInstallation::STATUS_ERROR) {
                    $installation->update([
                        'error_log' => [
                            [
                                'timestamp' => now()->subHours(2)->toISOString(),
                                'message' => 'Configuration validation failed',
                                'context' => ['field' => 'api_key', 'error' => 'Invalid API key format'],
                                'version' => $module->version
                            ]
                        ]
                    ]);
                }
            }
        }
    }

    private function createModuleReviews(array $modules): void
    {
        $this->command->info('⭐ Creating module reviews...');

        $users = User::all();
        
        foreach ($modules as $module) {
            // Create 5-15 reviews per module
            $reviewCount = rand(5, 15);
            $selectedUsers = $users->random(min($reviewCount, $users->count()));

            foreach ($selectedUsers as $user) {
                $rating = $this->generateRealisticRating($module->rating);
                
                ModuleReview::updateOrCreate([
                    'module_id' => $module->id,
                    'user_id' => $user->id
                ], [
                    'tenant_id' => $user->tenant_id,
                    'rating' => $rating,
                    'title' => $this->generateReviewTitle($rating),
                    'content' => $this->generateReviewContent($rating, $module->name),
                    'pros' => $this->generatePros($rating),
                    'cons' => $this->generateCons($rating),
                    'module_version' => $module->version,
                    'usage_duration' => collect([
                        'week_to_month',
                        'month_to_three_months',
                        'three_to_six_months',
                        'six_months_to_year'
                    ])->random(),
                    'recommendation' => $rating >= 4 ? 'yes' : ($rating >= 3 ? 'maybe' : 'no'),
                    'is_verified_purchase' => rand(0, 10) > 3, // 70% verified
                    'is_featured' => rand(0, 10) > 8, // 20% featured
                    'is_approved' => true,
                    'approved_by' => 1,
                    'approved_at' => now()->subDays(rand(1, 30)),
                    'helpful_count' => rand(0, 25),
                    'not_helpful_count' => rand(0, 5),
                    'tags' => $this->generateReviewTags($module->category),
                    'created_at' => now()->subDays(rand(1, 60))
                ]);
            }

            // Update module rating based on reviews
            $module->updateRating();
        }
    }

    private function generateRealisticRating(float $averageRating): int
    {
        // Generate ratings that cluster around the average
        $weights = [
            1 => $averageRating < 2.5 ? 30 : 5,
            2 => $averageRating < 3.0 ? 25 : 8,
            3 => $averageRating < 3.5 ? 20 : 12,
            4 => $averageRating > 4.0 ? 35 : 25,
            5 => $averageRating > 4.5 ? 40 : 20
        ];

        $total = array_sum($weights);
        $random = rand(1, $total);
        $cumulative = 0;

        foreach ($weights as $rating => $weight) {
            $cumulative += $weight;
            if ($random <= $cumulative) {
                return $rating;
            }
        }

        return 4; // fallback
    }

    private function generateReviewTitle(int $rating): string
    {
        $titles = [
            5 => [
                'Excellent module, highly recommended!',
                'Outstanding functionality and support',
                'Perfect solution for our needs',
                'Amazing features and easy to use',
                'Best module in its category'
            ],
            4 => [
                'Great module with minor issues',
                'Very good, does what it promises',
                'Solid choice for most use cases',
                'Good value for money',
                'Recommended with small reservations'
            ],
            3 => [
                'Decent module but has room for improvement',
                'Average functionality, nothing special',
                'Works but could be better',
                'Mixed feelings about this one',
                'Okay for basic needs'
            ],
            2 => [
                'Disappointing experience',
                'Too many issues to recommend',
                'Not worth the price',
                'Buggy and unreliable',
                'Expected much more'
            ],
            1 => [
                'Complete waste of time',
                'Broken and unusable',
                'Avoid at all costs',
                'Terrible experience',
                'Does not work as advertised'
            ]
        ];

        return collect($titles[$rating])->random();
    }

    private function generateReviewContent(int $rating, string $moduleName): string
    {
        $positiveContent = [
            "I've been using {$moduleName} for several months now and it has exceeded my expectations. The interface is intuitive and the features are well-implemented.",
            "This module has significantly improved our workflow. The installation was straightforward and the documentation is comprehensive.",
            "Excellent module with great support from the developers. Regular updates and new features keep it current.",
            "Really impressed with the quality of this module. It integrates seamlessly with our existing setup."
        ];

        $neutralContent = [
            "The module does what it's supposed to do, but I was expecting more advanced features. It's decent for basic use cases.",
            "Works as advertised but has some limitations. The documentation could be more detailed.",
            "It's an okay module but there are similar alternatives that might offer better value."
        ];

        $negativeContent = [
            "Unfortunately, this module didn't meet our expectations. We encountered several bugs and the support response was slow.",
            "The module has potential but it's not ready for production use. Too many stability issues.",
            "Disappointed with the purchase. The features don't work as described and setup was problematic."
        ];

        if ($rating >= 4) {
            return collect($positiveContent)->random();
        } elseif ($rating >= 3) {
            return collect($neutralContent)->random();
        } else {
            return collect($negativeContent)->random();
        }
    }

    private function generatePros(int $rating): array
    {
        $allPros = [
            'Easy to install and configure',
            'Great documentation',
            'Regular updates',
            'Responsive support team',
            'Clean and intuitive interface',
            'Good performance',
            'Extensive customization options',
            'Well-coded and secure',
            'Good value for money',
            'Seamless integration'
        ];

        $prosCount = $rating >= 4 ? rand(3, 5) : rand(1, 3);
        return collect($allPros)->random($prosCount)->toArray();
    }

    private function generateCons(int $rating): array
    {
        $allCons = [
            'Limited customization options',
            'Could use better documentation',
            'Some features are missing',
            'Performance could be improved',
            'Expensive for small teams',
            'Setup can be complex',
            'Some bugs in edge cases',
            'Support response time',
            'Interface could be more modern',
            'Limited integration options'
        ];

        $consCount = $rating <= 2 ? rand(3, 5) : rand(0, 2);
        return collect($allCons)->random($consCount)->toArray();
    }

    private function generateReviewTags(string $category): array
    {
        $categoryTags = [
            'analytics' => ['reporting', 'charts', 'dashboard', 'data-visualization'],
            'authentication' => ['security', 'login', '2fa', 'oauth'],
            'payment' => ['billing', 'stripe', 'paypal', 'checkout'],
            'communication' => ['email', 'notifications', 'sms', 'messaging'],
            'content' => ['cms', 'editor', 'media', 'seo'],
            'utility' => ['tools', 'helpers', 'performance', 'optimization'],
            'ecommerce' => ['shopping', 'cart', 'orders', 'inventory'],
            'workflow' => ['automation', 'tasks', 'processes', 'scheduling']
        ];

        $generalTags = ['easy-to-use', 'reliable', 'feature-rich', 'well-documented', 'good-support'];
        
        $categorySpecific = $categoryTags[$category] ?? [];
        $allTags = array_merge($categorySpecific, $generalTags);
        
        return collect($allTags)->random(rand(2, 4))->toArray();
    }

    private function decrementVersion(string $version): string
    {
        $parts = explode('.', $version);
        if (count($parts) >= 3) {
            $parts[2] = max(0, (int)$parts[2] - 1);
        }
        return implode('.', $parts);
    }

    private function incrementVersion(string $version, string $type = 'patch'): string
    {
        $parts = explode('.', $version);
        
        switch ($type) {
            case 'major':
                $parts[0] = (int)$parts[0] + 1;
                $parts[1] = 0;
                $parts[2] = 0;
                break;
            case 'minor':
                $parts[1] = (int)$parts[1] + 1;
                $parts[2] = 0;
                break;
            default: // patch
                $parts[2] = (int)$parts[2] + 1;
        }
        
        return implode('.', $parts);
    }

    private function printModuleCredentials(array $modules): void
    {
        $this->command->info('');
        $this->command->info('🧩 Demo Modules Created:');
        $this->command->info('========================');
        
        foreach ($modules as $module) {
            $this->command->info("• {$module->name} (v{$module->version})");
            $this->command->info("  Category: {$module->category} | Price: " . ($module->price > 0 ? '€' . number_format($module->price / 100, 2) : 'Free'));
            $this->command->info("  Rating: {$module->rating}⭐ | Downloads: {$module->download_count}");
            $this->command->info("  Slug: {$module->slug}");
            $this->command->info("");
        }
        
        $this->command->info('📊 Module Statistics:');
        $this->command->info('- Total Modules: ' . count($modules));
        $this->command->info('- Free Modules: ' . collect($modules)->where('price', 0)->count());
        $this->command->info('- Paid Modules: ' . collect($modules)->where('price', '>', 0)->count());
        $this->command->info('- Featured Modules: ' . collect($modules)->where('is_featured', true)->count());
        $this->command->info('- Average Rating: ' . round(collect($modules)->avg('rating'), 1));
    }
}