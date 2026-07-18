{{-- The demo's front door. It is the first thing a visitor sees after clicking
     "Try the demo" on kubo.global, so it wears that site's clothes: Work Sans on
     cream, ink headings, white cards, the yellow call to action. Only ever rendered
     in DEMO_MODE, so the school app itself carries none of this. --}}
<x-page title="Try the KUBO School Platform" bg-color="bg-[#f1eeec]">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;500;600;700;800;900&display=swap">

  <style>
    /* kubo.global's tokens, so the demo doesn't feel like a different product. */
    .site {
      --ink: #0c0822;
      --muted: #5f5a69;
      --line: #e8e3dc;
      --yellow: #ffcd31;
      --blue: #4a76b9;
      --card: #fff;
      --shadow: 0 1px 2px rgba(12,8,34,.05), 0 14px 36px rgba(12,8,34,.08);
      font-family: "Work Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
      color: var(--ink);
    }
    /* 900 on a 3rem heading, black avatars and a black bar made the page read as one
       dark block. Lighter: a 700 heading, blue avatars, more air. */
    .site h1 { font-size: clamp(1.8rem, 3.4vw, 2.4rem); line-height: 1.15; letter-spacing: -.01em; font-weight: 700; }
    .site .lead { font-size: clamp(1rem, 1.6vw, 1.1rem); color: var(--muted); max-width: 58ch; font-weight: 400; }
    .site .card {
      background: var(--card); border: 1px solid var(--line); border-radius: 16px;
      box-shadow: var(--shadow); padding: 22px; width: 100%; text-align: left; cursor: pointer;
      transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    }
    .site .card:hover { transform: translateY(-2px); border-color: var(--blue); box-shadow: 0 2px 4px rgba(12,8,34,.06), 0 18px 44px rgba(12,8,34,.12); }
    .site .card .role { font-weight: 700; font-size: 1.02rem; }
    .site .card .who { color: var(--muted); font-size: .92rem; }
    .site .card .blurb { color: var(--muted); font-size: .92rem; margin-top: 12px; line-height: 1.5; }
    .site .card .go { color: var(--blue); font-weight: 700; font-size: .92rem; margin-top: 10px; display: block; }
    .site .avatar {
      width: 42px; height: 42px; border-radius: 999px; background: #e9eff8; color: var(--blue);
      display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .82rem;
      text-transform: uppercase; flex: none;
    }
    .site .note { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 20px 22px; }
    .site .eyebrow { font-weight: 700; letter-spacing: .12em; text-transform: uppercase; font-size: .78rem; color: var(--blue); }
    .site .note a { color: var(--blue); font-weight: 700; }
    .site code { background: #f5f2ef; border: 1px solid var(--line); border-radius: 6px; padding: 1px 6px; font-size: .85em; }
    .site .logo { height: 40px; fill: var(--ink); }
  </style>

  <div class="site">
    <div class="max-w-5xl px-6 py-14 mx-auto">

      <svg class="logo" viewBox="0 0 238.63 231.78" role="img" aria-label="KUBO">
        <path d="M227.39,72.13l11.24-6.48L124.94,0,0,72.13,37.9,94v87.53l87,50.24,87-50.24V81l-87,50.26L49.13,87.53,113.7,50.25l-11.24-6.48L37.9,81,22.47,72.13,124.94,13ZM49.14,100.49l75.8,43.84,75.79-43.84V175l-75.79,43.83L49.14,175Z"/>
      </svg>

      {{-- kubo.global is a suite; this demo is one product of it, and the visitor
           should know which one they are standing in. --}}
      <p class="mt-8 eyebrow">KUBO School Platform</p>
      <h1 class="mt-2">Step into a school.</h1>
      <p class="mt-4 lead">
        A demo school with made-up pupils, scores and health records. Pick who you want to be:
        a headmaster, a teacher and a caregiver each see a different school. Switch whenever you like.
        Everything resets every night.
      </p>

      <div class="grid grid-cols-1 gap-4 mt-10 sm:grid-cols-2">
        @foreach ($people as $role => $person)
          @php $meta = \App\Http\Controllers\NewInterfaceControllers\DemoController::ROLES[$role]; @endphp

          {{-- A pupil isn't handed a session: they sign in the way children do, by
               picking their class and tapping their own name. That flow is part of
               what we're showing, so the pupil card walks into it. --}}
          @if ($role === 'student')
            <a href="{{ route('student-login.select-grade') }}" class="block card">
              <span class="flex items-center gap-3">
                <span class="avatar" aria-hidden="true">{{ $person->getInitials() }}</span>
                <span>
                  <span class="block role">{{ $meta['label'] }}</span>
                  <span class="block who">Sign in the way a child does</span>
                </span>
              </span>
              <span class="block blurb">{{ $meta['blurb'] }}</span>
              <span class="go">Pick a class, tap your name. No password &rarr;</span>
            </a>
            @continue
          @endif

          <form method="POST" action="{{ route('demo.login', $role) }}">
            @csrf
            <button type="submit" class="card">
              <span class="flex items-center gap-3">
                <span class="avatar" aria-hidden="true">{{ $person->getInitials() }}</span>
                <span>
                  <span class="block role">{{ $meta['label'] }}</span>
                  <span class="block who">{{ $person->getFullNameAttribute() }}</span>
                </span>
              </span>
              <span class="block blurb">{{ $meta['blurb'] }}</span>
            </button>
          </form>
        @endforeach
      </div>

      {{-- The sign-in screens are part of the product too: the demo hands out roles,
           but a visitor should see the door a real school actually uses. --}}
      <div class="mt-10 note">
        <p style="margin:0">
          <strong>Want to see how signing in really works?</strong>
          Staff pick their name and type a password on the
          <a href="{{ route('login', ['real' => 1]) }}">staff sign-in screen</a>
          (in this demo the password is <code>secret</code>), and children sign in on the
          <a href="{{ route('student-login.select-grade') }}">pupil screen</a>
          by picking their class and tapping their name, with no password to remember.
        </p>
      </div>

      <p class="mt-8 text-sm" style="color: var(--muted)">
        A real school runs the KUBO School Platform offline, on a server in the school itself.
        <a href="https://kubo.global" style="color: var(--blue); font-weight: 700">Back to kubo.global</a>
      </p>
    </div>
  </div>
</x-page>
