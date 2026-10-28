{{--
    En-tête d'une page de témoignage (/testimonies/{id}, /videos/{id}) : aperçu de partage, lien canonique
    (/testimonies/{id} sur l'adresse publique), données structurées VideoObject ou Article et fil d'Ariane.
    Indexée seulement si le témoignage est publié sur le web (docs/fonctionnalites/referencement.md).
--}}
<x-share-meta :meta="\App\Support\ShareMeta::testimony($testimony)" />
@if($testimony->isOnPublicWeb())
    {{ \App\Support\StructuredData::script(\App\Support\StructuredData::testimony($testimony)) }}
    {{ \App\Support\StructuredData::script(\App\Support\StructuredData::breadcrumb(array_values(array_filter([
        [__('seo.breadcrumb.home'), \App\Support\ShareMeta::absolute('/')],
        $testimony->category ? [$testimony->category->displayName(), \App\Support\ShareMeta::absolute(route('testimonies.category', $testimony->category->slug, false))] : null,
        [trim((string) $testimony->title), $testimony->share_url],
    ])))) }}
@endif
