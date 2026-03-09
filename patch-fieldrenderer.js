const fs = require('fs');
const f = 'C:/Users/ianda/git-repos/claude-member-directory-v7/includes/FieldRenderer.php';
let src = fs.readFileSync(f, 'utf8');
const lines = src.split('\n');

// Find line indices (0-based) for the key markers
const truefalseCommentLine = lines.findIndex(l => l.includes('true_false') && l.includes('\u2500\u2500'));
const valueAssignLine = lines.findIndex(l => l.includes("$value = get_field( $key, $post_id )") && !l.includes('is_empty'));

if (truefalseCommentLine === -1 || valueAssignLine === -1) {
	console.error('Could not find target lines');
	console.log('truefalseCommentLine:', truefalseCommentLine, 'valueAssignLine:', valueAssignLine);
	process.exit(1);
}

console.log('Replacing lines', truefalseCommentLine + 1, 'through', valueAssignLine + 1);

// Replace lines from the true_false comment through the $value assignment
const replacement = [
	"\t\t// -- Fetch value once for all types ------------------------------------",
	"\t\t// PERF: A single get_field() call serves every type below, including",
	"\t\t// true_false and wysiwyg which previously read it again inside their",
	"\t\t// own renderers. The pre-fetched $value is passed through so no",
	"\t\t// type-specific renderer needs its own DB round-trip.",
	"\t\t//",
	"\t\t// Revert: remove $value here; restore get_field() calls inside",
	"\t\t// render_true_false() and render_wysiwyg().",
	"\t\t// ---------------------------------------------------------------------",
	"\t\t$value = get_field( $key, $post_id );",
	"",
	"\t\t// -- true_false -------------------------------------------------------",
	"\t\t// false (0) is a meaningful value (\"No\") and must always render.",
	"\t\t// Bypasses the standard empty-value guard used by all other types.",
	"\t\tif ( $type === 'true_false' ) {",
	"\t\t\tself::render_true_false( $value, $label );",
	"\t\t\treturn;",
	"\t\t}",
	"",
	"\t\t// -- wysiwyg ----------------------------------------------------------",
	"\t\t// ACF's the_field() must be used for output -- it applies wpautop",
	"\t\t// and shortcode expansion that get_field() would bypass.",
	"\t\t// The $value fetched above is only used for the emptiness check;",
	"\t\t// the_field() still handles final output.",
	"\t\tif ( $type === 'wysiwyg' ) {",
	"\t\t\tself::render_wysiwyg( $value, $key, $label, $post_id );",
	"\t\t\treturn;",
	"\t\t}",
];

lines.splice(truefalseCommentLine, valueAssignLine - truefalseCommentLine + 1, ...replacement);

// Now fix render_true_false signature
const tfSigLine = lines.findIndex(l => l.includes('render_true_false( string $key, string $label, int $post_id )'));
if (tfSigLine === -1) {
	console.error('Could not find render_true_false signature');
	process.exit(1);
}
console.log('Patching render_true_false at line', tfSigLine + 1);

const tfValueLine = lines.findIndex((l, i) => i > tfSigLine && l.includes('$value = get_field( $key, $post_id )'));
if (tfValueLine === -1) {
	console.error('Could not find $value = get_field inside render_true_false');
	process.exit(1);
}

// Replace signature and remove the get_field line
lines[tfSigLine] = [
	"\t// PERF: $value is passed in from render() -- no second get_field() call.",
	"\t// Revert: change signature back to (string $key, string $label, int $post_id)",
	"\t//         and add  $value = get_field( $key, $post_id );  as the first line.",
	"\tprivate static function render_true_false( mixed $value, string $label ): void {",
].join('\n');

// Remove the old $value = get_field line and its blank line after if present
lines.splice(tfValueLine, lines[tfValueLine + 1] === '' ? 2 : 1);

// Now fix render_wysiwyg signature
const wySigLine = lines.findIndex(l => l.includes('render_wysiwyg( string $key, string $label, int $post_id )'));
if (wySigLine === -1) {
	console.error('Could not find render_wysiwyg signature');
	process.exit(1);
}
console.log('Patching render_wysiwyg at line', wySigLine + 1);

const wyEmptyCheckLine = lines.findIndex((l, i) => i > wySigLine && l.includes('is_empty( get_field('));
if (wyEmptyCheckLine === -1) {
	console.error('Could not find is_empty(get_field()) in render_wysiwyg');
	process.exit(1);
}

// Replace signature
lines[wySigLine] = [
	"\t// PERF: $value is passed in from render() -- no second get_field() call.",
	"\t// the_field() is still used for final output (applies wpautop + shortcodes).",
	"\t// Revert: change signature back to (string $key, string $label, int $post_id)",
	"\t//         and replace is_empty($value) with is_empty(get_field($key,$post_id)).",
	"\tprivate static function render_wysiwyg( mixed $value, string $key, string $label, int $post_id ): void {",
].join('\n');

// Replace is_empty( get_field( $key, $post_id ) ) with is_empty( $value )
lines[wyEmptyCheckLine] = lines[wyEmptyCheckLine].replace(
	'is_empty( get_field( $key, $post_id ) )',
	'is_empty( $value )'
);

fs.writeFileSync(f, lines.join('\n'), 'utf8');
console.log('FieldRenderer.php patched successfully.');
