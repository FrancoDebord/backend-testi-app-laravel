{{--
    Référencement commun à toutes les pages (docs/fonctionnalites/referencement.md), dans le <head> des gabarits :
    - robots : noindex par défaut (compte, administration, recherche, profils…) ; une page publique l'ouvre avec
      @section('robots', 'index, follow') ;
    - Organization et WebSite (JSON-LD) ; vérification Google Search Console ; application iOS.
--}}
    <meta name="robots" content="{{ trim($__env->yieldContent('robots', 'noindex, follow')) }}">
@if(config('seo.google_site_verification'))
    <meta name="google-site-verification" content="{{ config('seo.google_site_verification') }}">
@endif
@if(config('seo.ios_app_store_id'))
    <meta name="apple-itunes-app" content="app-id={{ config('seo.ios_app_store_id') }}">
@endif
    {{ \App\Support\StructuredData::script([\App\Support\StructuredData::organization(), \App\Support\StructuredData::website()]) }}
