<?php
// Run with: php ascend-site-updates/tests/engine-test.php
require __DIR__ . '/../includes/engine.php';

$fails = 0;
function check( $label, $cond ) {
	global $fails;
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $cond ) {
		$fails++;
	}
}

$page = array(
	array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array(
		array( 'id' => 'h1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Hello', 'header_size' => 'h5' ), 'elements' => array() ),
		array( 'id' => 'ph', 'elType' => 'widget', 'widgetType' => 'icon-box', 'settings' => array( 'link' => array( 'url' => 'tel:+1386385-7653' ) ), 'elements' => array() ),
		array( 'id' => 'ls', 'elType' => 'widget', 'widgetType' => 'icon-list', 'settings' => array( 'icon_list' => array(
			array( '_id' => 'a', 'text' => 'Recordkeeping' ),
			array( '_id' => 'b', 'text' => 'Field Trips and Workshops (coming soon!)' ),
		) ), 'elements' => array() ),
	) ),
	array( 'id' => 'c2', 'elType' => 'container', 'settings' => array(), 'elements' => array(
		array( 'id' => 'tx', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>Please selecting the listed course.</p>' ), 'elements' => array() ),
	) ),
);

// replace in nested setting, scoped
list( $p, $s ) = asu_apply_change( $page, array( 'type' => 'replace', 'element' => 'ph', 'setting' => 'link.url', 'find' => 'tel:+1386385-7653', 'replace' => 'tel:+13863857653' ) );
check( 'replace nested link applied', 'applied' === $s && 'tel:+13863857653' === $p[0]['elements'][1]['settings']['link']['url'] );
list( , $s2 ) = asu_apply_change( $p, array( 'type' => 'replace', 'element' => 'ph', 'setting' => 'link.url', 'find' => 'tel:+1386385-7653', 'replace' => 'tel:+13863857653' ) );
check( 'replace is idempotent', 'done' === $s2 );

// unscoped replace anywhere
list( $p, $s ) = asu_apply_change( $p, array( 'type' => 'replace', 'find' => 'Please selecting', 'replace' => 'Please select' ) );
check( 'unscoped replace', 'applied' === $s && false !== strpos( $p[1]['elements'][0]['settings']['editor'], 'Please select the' ) );

// count guard
list( $p2, $s ) = asu_apply_change( $p, array( 'type' => 'replace', 'find' => 'e', 'replace' => 'E', 'count' => 1 ) );
check( 'count guard leaves tree unchanged', 'missing' === $s && $p2 === $p );

// missing
list( , $s ) = asu_apply_change( $p, array( 'type' => 'replace', 'find' => 'nope', 'replace' => 'x' ) );
check( 'missing text reported', 'missing' === $s );

// set
list( $p, $s ) = asu_apply_change( $p, array( 'type' => 'set', 'element' => 'h1', 'setting' => 'header_size', 'value' => 'h1' ) );
check( 'set header size', 'applied' === $s && 'h1' === $p[0]['elements'][0]['settings']['header_size'] );
list( , $s ) = asu_apply_change( $p, array( 'type' => 'set', 'element' => 'h1', 'setting' => 'header_size', 'value' => 'h1' ) );
check( 'set idempotent', 'done' === $s );
list( $p, $s ) = asu_apply_change( $p, array( 'type' => 'set', 'element' => 'h1', 'setting' => 'header_size', 'value' => null ) );
check( 'set null removes key', 'applied' === $s && ! isset( $p[0]['elements'][0]['settings']['header_size'] ) );

// remove_item
list( $p, $s ) = asu_apply_change( $p, array( 'type' => 'remove_item', 'element' => 'ls', 'setting' => 'icon_list', 'match_key' => 'text', 'match' => 'Field Trips' ) );
check( 'remove repeater item', 'applied' === $s && 1 === count( $p[0]['elements'][2]['settings']['icon_list'] ) );

// add_item
$item = array( '_id' => 'z', 'text' => 'Official transcript' );
list( $p, $s ) = asu_apply_change( $p, array( 'type' => 'add_item', 'element' => 'ls', 'setting' => 'icon_list', 'item' => $item, 'match_key' => 'text', 'position' => 0 ) );
check( 'add repeater item at position', 'applied' === $s && 'Official transcript' === $p[0]['elements'][2]['settings']['icon_list'][0]['text'] );
list( , $s ) = asu_apply_change( $p, array( 'type' => 'add_item', 'element' => 'ls', 'setting' => 'icon_list', 'item' => $item, 'match_key' => 'text' ) );
check( 'add_item idempotent', 'done' === $s );

// insert after (nested) / at end / append_to
$new = array( 'id' => 'nw', 'elType' => 'widget', 'widgetType' => 'html', 'settings' => array( 'html' => '<p>x</p>' ), 'elements' => array() );
list( $p, $s ) = asu_apply_change( $p, array( 'type' => 'insert', 'after' => 'h1', 'new' => $new ) );
check( 'insert after nested widget', 'applied' === $s && 'nw' === $p[0]['elements'][1]['id'] );
list( , $s ) = asu_apply_change( $p, array( 'type' => 'insert', 'after' => 'h1', 'new' => $new ) );
check( 'insert idempotent', 'done' === $s );
$new2 = array( 'id' => 'en', 'elType' => 'container', 'settings' => array(), 'elements' => array() );
list( $p, $s ) = asu_apply_change( $p, array( 'type' => 'insert', 'at_end' => true, 'new' => $new2 ) );
check( 'insert at end', 'applied' === $s && 'en' === end( $p )['id'] );
$new3 = array( 'id' => 'ap', 'elType' => 'widget', 'widgetType' => 'html', 'settings' => array(), 'elements' => array() );
list( $p, $s ) = asu_apply_change( $p, array( 'type' => 'insert', 'before' => 'tx', 'new' => $new3 ) );
check( 'insert before', 'applied' === $s && 'ap' === $p[1]['elements'][0]['id'] );
list( , $s ) = asu_apply_change( $p, array( 'type' => 'insert', 'after' => 'zzz', 'new' => array( 'id' => 'q1' ) ) );
check( 'insert with missing anchor', 'missing' === $s );

// remove
list( $p, $s ) = asu_apply_change( $p, array( 'type' => 'remove', 'element' => 'nw' ) );
check( 'remove element', 'applied' === $s && ! asu_element_exists( $p, 'nw' ) );

// replace_all
list( $p, $s ) = asu_apply_change( $p, array( 'type' => 'replace_all', 'elements' => array( array( 'id' => 'pp', 'elements' => array() ) ) ) );
check( 'replace_all', 'applied' === $s && 'pp' === $p[0]['id'] );
list( , $s ) = asu_apply_change( $p, array( 'type' => 'replace_all', 'elements' => array( array( 'id' => 'pp', 'elements' => array() ) ) ) );
check( 'replace_all idempotent', 'done' === $s );

// set_item by matching title
$acc = array( array( 'id' => 'ac', 'elType' => 'widget', 'widgetType' => 'ucaddon_content_accordion', 'settings' => array( 'uc_items' => array(
	array( '_id' => '1', 'title' => 'How many days?', 'content' => '<p>180 calendar days</p>', 'link' => array( 'url' => 'x.com/why' ) ),
	array( '_id' => '2', 'title' => 'Can we withdraw?', 'content' => '<div id="content">junk</div>' ),
) ), 'elements' => array() ) );
list( $acc, $s ) = asu_apply_change( $acc, array( 'type' => 'set_item', 'element' => 'ac', 'setting' => 'uc_items', 'match_key' => 'title', 'match' => 'withdraw', 'key' => 'content', 'value' => '<p>Yes.</p>' ) );
check( 'set_item by title', 'applied' === $s && '<p>Yes.</p>' === $acc[0]['settings']['uc_items'][1]['content'] );
list( , $s ) = asu_apply_change( $acc, array( 'type' => 'set_item', 'element' => 'ac', 'setting' => 'uc_items', 'match_key' => 'title', 'match' => 'withdraw', 'key' => 'content', 'value' => '<p>Yes.</p>' ) );
check( 'set_item idempotent', 'done' === $s );
list( $acc, $s ) = asu_apply_change( $acc, array( 'type' => 'set_item', 'element' => 'ac', 'setting' => 'uc_items', 'match_key' => 'title', 'match' => 'How many', 'key' => 'link.url', 'value' => 'https://x.com/why/' ) );
check( 'set_item nested key', 'applied' === $s && 'https://x.com/why/' === $acc[0]['settings']['uc_items'][0]['link']['url'] );
list( , $s ) = asu_apply_change( $acc, array( 'type' => 'set_item', 'element' => 'ac', 'setting' => 'uc_items', 'match_key' => 'title', 'match' => 'nope', 'key' => 'content', 'value' => 'x' ) );
check( 'set_item no match', 'missing' === $s );

// deletions report done once applied
list( $acc2, $s ) = asu_apply_change( $acc, array( 'type' => 'replace', 'element' => 'ac', 'find' => '180 calendar', 'replace' => '', 'done_if_absent' => true ) );
list( , $s2 ) = asu_apply_change( $acc2, array( 'type' => 'replace', 'element' => 'ac', 'find' => '180 calendar', 'replace' => '', 'done_if_absent' => true ) );
check( 'deletion then done', 'applied' === $s && 'done' === $s2 );

echo $fails ? "\n$fails failure(s)\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
