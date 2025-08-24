{{-- resources/views/layouts/navigation.blade.php --}}
<nav class="bg-white dark:bg-gray-800 border-b dark:border-gray-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            {{-- Left: Brand + primary links --}}
            <div class="flex items-center">
                <a href="{{ url('/') }}" class="flex items-center">
          <span class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            FDS Dashboard
          </span>
                </a>

                <div class="hidden sm:flex sm:ml-6 space-x-6">
                    <a href="{{ url('/dashboard') }}"
                       class="inline-flex items-center px-1 pt-1 text-sm {{ request()->is('dashboard') ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-300' }}">
                        Dashboard
                    </a>
                    <a href="{{ url('/admin') }}"
                       class="inline-flex items-center px-1 pt-1 text-sm {{ request()->is('admin') ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-300' }}">
                        Admin
                    </a>
                </div>
            </div>

            {{-- Right: Auth controls (guest-safe) --}}
            <div class="hidden sm:flex sm:items-center sm:ml-6">
                @auth
                    <div class="flex items-center space-x-4">
            <span class="text-sm text-gray-700 dark:text-gray-300">
              {{ auth()->user()?->name ?? 'User' }}
            </span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="text-sm text-gray-600 dark:text-gray-300 hover:underline">
                                Log out
                            </button>
                        </form>
                    </div>
                @else
                    <div class="flex items-center space-x-4">
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}"
                               class="text-sm text-gray-600 dark:text-gray-300 hover:underline">
                                Log in
                            </a>
                        @endif
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}"
                               class="text-sm text-gray-600 dark:text-gray-300 hover:underline">
                                Register
                            </a>
                        @endif
                    </div>
                @endauth
            </div>

            {{-- Mobile menu placeholder (optional) --}}
            <div class="-mr-2 flex items-center sm:hidden">
                {{-- If you use Alpine/JS for a mobile menu, wire it here --}}
            </div>
        </div>
    </div>
</nav>
