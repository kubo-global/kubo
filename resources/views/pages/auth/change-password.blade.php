<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>KUBO | Set your password</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    <div class="flex flex-col items-center justify-center min-h-screen px-6 py-12 bg-gradient-to-br from-indigo-500 via-indigo-700 to-indigo-900">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center">
                <svg class="w-auto h-16 mx-auto text-white fill-current drop-shadow" viewBox="0 0 240 240" xmlns="http://www.w3.org/2000/svg">
                    <path d="M228.39 76.13l11.24-6.48L125.94 4 1 76.13 38.9 98v87.53l87 50.24 87-50.24V85l-87 50.26-75.77-43.73 64.57-37.28-11.24-6.48L38.9 85l-15.43-8.87L125.94 17l102.45 59.13zM50.14 104.49l75.8 43.84 75.79-43.84V179l-75.79 43.83L50.14 179v-74.51z"/>
                </svg>
                <h2 class="mt-6 text-2xl font-extrabold tracking-tight text-white">Set your password</h2>
                <p class="mt-2 text-sm text-indigo-100">Choose a password you'll remember — you'll use it to sign in from now on.</p>
            </div>

            @foreach ($errors->all() as $error)
                <div class="p-3 mb-4 text-sm text-white border rounded-lg bg-red-500/25 border-red-300/40">{{ $error }}</div>
            @endforeach

            <form method="POST" action="{{ route('password.update') }}" class="space-y-5" x-data="{ show: false }">
                @csrf
                <div>
                    <label for="password" class="block mb-1.5 text-sm font-medium text-white/90">New password</label>
                    <input id="password" name="password" required minlength="4" :type="show ? 'text' : 'password'" autofocus
                        class="w-full px-3.5 py-2.5 text-white border rounded-lg appearance-none bg-white/10 border-white/25 placeholder-white/60 backdrop-blur-sm focus:outline-none focus:bg-white/20 focus:border-white/60 focus:ring-2 focus:ring-white/40 transition">
                </div>
                <label class="flex items-center gap-2 text-sm cursor-pointer text-white/80">
                    <input type="checkbox" x-model="show" class="rounded"> Show password
                </label>
                <button type="submit"
                    class="flex justify-center w-full px-4 py-2.5 text-sm font-semibold text-indigo-700 transition bg-white rounded-lg shadow-lg hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-white/70">
                    Save &amp; continue
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="{{ route('logout') }}" class="text-sm text-white/70 hover:text-white">Not you? Sign out</a>
            </div>
        </div>
    </div>
</body>
</html>
