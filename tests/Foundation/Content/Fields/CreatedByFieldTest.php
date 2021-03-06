<?php

use Flextype\Component\Filesystem\Filesystem;

beforeEach(function() {
    filesystem()->directory(PATH['project'] . '/entries')->create();
});

afterEach(function (): void {
    filesystem()->directory(PATH['project'] . '/entries')->delete();
});

test('test CreatedByField', function () {
    flextype('content')->create('foo', []);
    $created_by = flextype('content')->fetch('foo')['created_by'];
    $this->assertEquals('', $created_by);

    flextype('content')->create('bar', ['created_by' => 'Zed']);
    $created_by = flextype('content')->fetch('bar')['created_by'];
    $this->assertEquals('Zed', $created_by);
});
