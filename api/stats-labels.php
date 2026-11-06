<?php
/*
 * The events the site may record, with the names shown on the stats page.
 * Anything not listed here is ignored by track.php.
 */

return [
    'open' => [
        'hunger' => 'Was it repentance, or just hunger?',
        'position' => 'The position was never open.',
        'embrace' => 'The embrace before the explanation.',
        'outside' => 'So close to home. Still outside.',
        'privacy' => 'Privacy note',
    ],
    'click' => [
        'discover-book' => 'Discover the book (hero)',
        'explore-insights' => 'Explore the insights (hero)',
        'nav-join' => 'Join the journey (header)',
        'book-notify' => 'Tell me when it’s published (book)',
        'youtube-section' => 'YouTube link (video section)',
        'youtube-footer' => 'YouTube link (footer)',
    ],
    'scroll' => [
        '25' => 'A quarter of the page',
        '50' => 'Half the page',
        '75' => 'Three quarters of the page',
        '100' => 'The end of the page',
    ],
];
