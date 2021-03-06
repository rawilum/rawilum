<?php

declare(strict_types=1);

/**
 * Flextype (https://flextype.org)
 * Founded by Sergey Romanenko and maintained by Flextype Community.
 */

if (flextype('registry')->get('flextype.settings.entries.content.fields.published_at.enabled')) {
    flextype('emitter')->addListener('onContentFetchSingleHasResult', static function (): void {
        if (flextype('content')->registry()->get('fetch.data.published_at') === null) {
            flextype('content')->registry()->set('fetch.data.published_at', (int) filesystem()->file(flextype('content')->getFileLocation(flextype('content')->registry()->get('fetch.id')))->lastModified());
        } else {
            flextype('content')->registry()->set('fetch.data.published_at', (int) strtotime((string) flextype('content')->registry()->get('fetch.data.published_at')));
        }
    });

    flextype('emitter')->addListener('onContentCreate', static function (): void {
        if (flextype('content')->registry()->get('create.data.published_at') !== null) {
            return;
        }

        flextype('content')->registry()->set('create.data.published_at', date(flextype('registry')->get('flextype.settings.date_format'), time()));
    });
}
