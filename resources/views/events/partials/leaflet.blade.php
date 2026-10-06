{{-- Leaflet 1.9.4 (cartes OpenStreetMap des événements), chargé une seule fois par page. --}}
@once
@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css"
      integrity="sha512-Zcn6bjR/8RZbLEpLIeOwNtzREBAJnUKESxces60Mpoj+2okopSAcSUIUOseddDm0cxnGQzxIR7vJgsLZbdLE3w==" crossorigin="anonymous" referrerpolicy="no-referrer">
<style>
    .event-map { z-index: 0; }
    .event-map .leaflet-control-attribution { font-size: 10px; }
</style>
@endpush
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"
        integrity="sha512-BwHfrr4c9kmRkLw6iXFdzcdWV/PGkVgiIyIWLLlTSXzWQzxuSg4DiQUCpauz/EWjgk5TYQqX/kvn9pG1NpYfqg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    // Tuiles OpenStreetMap : attribution obligatoire (https://www.openstreetmap.org/copyright).
    window.testiOsmLayer = function () {
        return L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">contributeurs OpenStreetMap</a>',
        });
    };
</script>
@endpush
@endonce
