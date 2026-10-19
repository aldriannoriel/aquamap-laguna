        <header class="border-b border-line bg-white">
            <div class="mx-auto flex max-w-[1600px] flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-8">
                <a href="{{ route('map.index') }}" class="flex items-center gap-3 rounded-lg" aria-label="AquaMap Laguna home">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-estuary text-white" aria-hidden="true">
                        <svg viewBox="0 0 32 32" class="size-8 fill-none" stroke="currentColor" stroke-width="1.7"><path d="M16 3C12 9 7 14 7 19a9 9 0 0 0 18 0c0-5-5-10-9-16Z"/><path d="M8 18c4-4 7 4 11 0s5-1 5-1M10 23c4-3 6 3 11 0"/></svg>
                    </span>
                    <span><span class="block text-xl font-semibold tracking-tight">AquaMap <span class="font-normal text-muted">Laguna</span></span><span class="block text-xs text-muted">Community environmental awareness</span></span>
                </a>
                <nav aria-label="Main navigation" class="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm">
                    <a href="{{ route('map.index') }}" @if(request()->routeIs('map.index')) aria-current="page" @endif @class(['border-b-2 py-2 font-semibold text-estuary', 'border-estuary' => request()->routeIs('map.index'), 'border-transparent' => !request()->routeIs('map.index')])>Water explorer</a>
                    <a href="{{ route('reports.create') }}" @if(request()->routeIs('reports.*')) aria-current="page" @endif @class(['border-b-2 py-2 font-semibold text-estuary', 'border-estuary' => request()->routeIs('reports.*'), 'border-transparent' => !request()->routeIs('reports.*')])>Report a concern</a>
                    <a href="{{ route('map.index') }}#research-notes" class="py-2 text-muted hover:text-estuary">About the data</a>
                    <a href="{{ route('admin.dashboard') }}" class="py-2 text-muted hover:text-estuary">Administrator</a>
                </nav>
            </div>
        </header>
