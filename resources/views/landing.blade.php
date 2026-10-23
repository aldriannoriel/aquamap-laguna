<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Discover Laguna's lakes, rivers, waterfalls and aquatic life on AquaMap Laguna.">
    <title>AquaMap Laguna | Discover Laguna's Waters</title>
    @vite('resources/css/landing.css')
</head>
<body class="aquamap-landing">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <main id="main-content" tabindex="-1">
        <section class="hero" aria-labelledby="hero-title">
            <header class="site-header">
                <nav class="nav shell" aria-label="Main navigation">
                    <a href="{{ route('home') }}" class="brand" aria-label="AquaMap Laguna home">
                        <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M16 3C12 9 7 14 7 19a9 9 0 0 0 18 0c0-5-5-10-9-16Z"/><path d="M8 18c4-4 7 4 11 0s5-1 5-1M10 23c4-3 6 3 11 0"/></svg></span>
                        <span><span class="brand-name">AquaMap <span>Laguna</span></span><span class="brand-sub">Community environmental awareness</span></span>
                    </a>
                    <div class="nav-links">
                        <a href="{{ route('home') }}" aria-current="page">Home</a>
                        <a href="{{ route('reports.create') }}">Report a Concern</a>
                        <a href="{{ route('admin.dashboard') }}">Administrator</a>
                        <a href="{{ route('map.index') }}" class="nav-action">Open Water Explorer →</a>
                    </div>
                </nav>
            </header>
            <div class="shell"><div class="hero-inner">
                <p class="eyebrow">Explore · Learn · Protect</p>
                <h1 id="hero-title">Discover<br>Laguna's Waters.</h1>
                <p class="hero-description">Explore the lakes, rivers, and waterfalls of Laguna. Learn about their natural surroundings, discover aquatic life, and help protect our waters.</p>
                <div class="actions">
                    <a class="btn btn-primary" href="{{ route('map.index') }}">Explore the Map <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 14 0m-6-6 6 6-6 6"/></svg></a>
                    <a class="btn btn-secondary" href="{{ route('reports.create') }}">Report a Concern</a>
                </div>
            </div></div>
            <div class="feature-wrap"><div class="features shell">
                <a href="{{ route('map.index') }}" class="feature-card"><span class="feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3zM9 3v15m6-12v15"/></svg></span><div class="feature-copy"><h2>Explore Water Bodies</h2><p>Find lakes, rivers, and waterfalls on our interactive map.</p></div></a>
                <a href="{{ route('map.index') }}" class="feature-card"><span class="feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12c5-7 12-7 18 0-6 7-13 7-18 0zm14-4v8M6 11h.01"/></svg></span><div class="feature-copy"><h2>Learn About Aquatic Life</h2><p>Read about fish and their possible habitats.</p></div></a>
                <a href="{{ route('reports.create') }}" class="feature-card"><span class="feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 2 21h20L12 3Zm0 6v5m0 4h.01"/></svg></span><div class="feature-copy"><h2>Report a Concern</h2><p>Tell us if you notice pollution or other water-related issues.</p></div></a>
            </div></div>
        </section>
        <div class="content"><div class="shell">
            <section class="intro" aria-labelledby="intro-heading">
                <div><p class="section-kicker">About AquaMap Laguna</p><h2 id="intro-heading">A closer look at the waters around us.</h2></div>
                <div><p>Laguna is home to many rivers, lakes, and waterfalls. AquaMap Laguna brings available information about these places together in one easy-to-explore website.</p><p>Browse the map, read about local aquatic life, and share environmental concerns. Information about possible fish habitats is not proof that a species has been found at a location.</p></div>
            </section>
            <section class="info-band" aria-label="Explore the map"><div><strong>Ready to explore?</strong><p>Open the interactive map and choose a water body to learn more.</p></div><a href="{{ route('map.index') }}">Open the Water Explorer →</a></section>
        </div></div>
    </main>
    <footer><div class="foot shell"><span>© {{ date('Y') }} AquaMap Laguna. Built for environmental awareness.</span><nav aria-label="Footer navigation"><a href="{{ route('map.index') }}">Water Explorer</a><a href="{{ route('admin.dashboard') }}">Administrator</a></nav></div></footer>
</body>
</html>
