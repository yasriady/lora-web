<?php

/**
 * Temporary stubs for packages removed from composer.lock
 * while bootstrap/cache/packages.php still references them.
 */

namespace Inertia {
    if (! class_exists(ServiceProvider::class, false)) {
        class ServiceProvider extends \Illuminate\Support\ServiceProvider
        {
            public function register(): void
            {
            }

            public function boot(): void
            {
            }
        }
    }
}

namespace Laravel\Breeze {
    if (! class_exists(BreezeServiceProvider::class, false)) {
        class BreezeServiceProvider extends \Illuminate\Support\ServiceProvider
        {
            public function register(): void
            {
            }

            public function boot(): void
            {
            }
        }
    }
}

namespace Laravel\Pail {
    if (! class_exists(PailServiceProvider::class, false)) {
        class PailServiceProvider extends \Illuminate\Support\ServiceProvider
        {
            public function register(): void
            {
            }

            public function boot(): void
            {
            }
        }
    }
}

namespace Laravel\Sail {
    if (! class_exists(SailServiceProvider::class, false)) {
        class SailServiceProvider extends \Illuminate\Support\ServiceProvider
        {
            public function register(): void
            {
            }

            public function boot(): void
            {
            }
        }
    }
}

namespace Laravel\Sanctum {
    if (! class_exists(SanctumServiceProvider::class, false)) {
        class SanctumServiceProvider extends \Illuminate\Support\ServiceProvider
        {
            public function register(): void
            {
            }

            public function boot(): void
            {
            }
        }
    }
}

namespace Spatie\Permission {
    if (! class_exists(PermissionServiceProvider::class, false)) {
        class PermissionServiceProvider extends \Illuminate\Support\ServiceProvider
        {
            public function register(): void
            {
            }

            public function boot(): void
            {
            }
        }
    }
}

namespace Tighten\Ziggy {
    if (! class_exists(ZiggyServiceProvider::class, false)) {
        class ZiggyServiceProvider extends \Illuminate\Support\ServiceProvider
        {
            public function register(): void
            {
            }

            public function boot(): void
            {
            }
        }
    }
}
