<script setup>
import { computed, reactive, ref, watch } from 'vue';

const input = ref('');
const copied = ref(false);
const inputLayer = ref(null);

const escapeHtml = (s) =>
  s.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' })[c]);

function highlightJson(text) {
  return escapeHtml(text).replace(
    /("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(\.\d+)?([eE][+-]?\d+)?)/g,
    (match) => {
      let cls = 'json-number';
      if (/^"/.test(match)) cls = /:$/.test(match) ? 'json-key' : 'json-string';
      else if (/true|false/.test(match)) cls = 'json-boolean';
      else if (/null/.test(match)) cls = 'json-null';
      return `<span class="${cls}">${match}</span>`;
    },
  );
}

function syncScroll(e) {
  if (!inputLayer.value) return;
  inputLayer.value.scrollTop = e.target.scrollTop;
  inputLayer.value.scrollLeft = e.target.scrollLeft;
}

// schemastore.org doesn't send CORS headers, but its schemas are mirrored
// on GitHub (SchemaStore/schemastore), and jsdelivr's GitHub CDN does send
// them — so route just that host through jsdelivr instead of a proxy.
function schemastoreMirror(url) {
  const { hostname, pathname } = new URL(url);
  if (!hostname.endsWith('schemastore.org')) return null;
  return `https://cdn.jsdelivr.net/gh/SchemaStore/schemastore@master/src/schemas/json${pathname}`;
}

function getSchemaOrder(schema) {
  return Object.keys(schema.properties || {});
}

function buildOrderMap(schema, map = {}, current = '#') {
  const properties = schema.properties || {};
  map[current] = getSchemaOrder(schema);

  for (const [key, value] of Object.entries(properties)) {
    if (value && typeof value === 'object') {
      buildOrderMap(value, map, key);
    }
  }

  return map;
}

function sortObject(obj, orderMap = {}, isRoot = false) {
  if (Array.isArray(obj)) {
    return obj.map((item) => sortObject(item, orderMap));
  }

  if (obj === null || typeof obj !== 'object') {
    return obj;
  }

  const result = {};

  // $schema isn't part of the schema's own properties, but by convention
  // (composer.json, JSON Schema Store, ...) it's expected to be first.
  if (isRoot && '$schema' in obj) {
    result.$schema = obj.$schema;
  }

  const currentOrder = orderMap['#'] || [];

  for (const key of currentOrder) {
    if (key in obj) {
      result[key] = sortObject(obj[key], {
        ...orderMap,
        '#': orderMap[key] || [],
      });
    }
  }

  const remainingKeys = Object.keys(obj)
    .filter((key) => !(key in result))
    .sort();

  for (const key of remainingKeys) {
    result[key] = sortObject(obj[key], orderMap);
  }

  return result;
}

// Pure: parse the input, nothing else. Errors live alongside the value
// instead of a separate ref, so there's one source of truth per keystroke.
const parsed = computed(() => {
  if (!input.value.trim()) return { json: null, error: '' };
  try {
    return { json: JSON.parse(input.value), error: '' };
  } catch (e) {
    return { json: null, error: `Invalid JSON: ${e.message}` };
  }
});

// Schema cache as a reactive Map: .get() is tracked per-key, so only
// components reading a given URL re-render when that URL resolves.
const schemaCache = reactive(new Map());

watch(
  () => parsed.value.json?.$schema,
  async (url) => {
    if (!url || schemaCache.has(url)) return;
    try {
      let response;
      try {
        response = await fetch(url);
      } catch (e) {
        const mirror = e instanceof TypeError && schemastoreMirror(url);
        if (!mirror) throw e;
        response = await fetch(mirror);
      }
      if (!response.ok)
        throw new Error(`${response.status} ${response.statusText}`);
      schemaCache.set(url, { schema: await response.json() });
    } catch (e) {
      schemaCache.set(url, { fetchError: e.message });
    }
  },
  { immediate: true },
);

// Pure: everything the template needs, derived from parsed input + cache.
const result = computed(() => {
  const { json, error } = parsed.value;
  const empty = { text: '', status: '', schemaUrl: '', error };
  if (error || !json) return empty;

  const url = json.$schema;
  if (!url) {
    return {
      ...empty,
      status: 'No $schema found — sorted keys alphabetically.',
      text: JSON.stringify(sortObject(json), null, 2),
    };
  }

  const cached = schemaCache.get(url);
  if (!cached) return { ...empty, status: `Loading schema: ${url}` };
  if (cached.fetchError)
    return { ...empty, error: `Failed to fetch schema: ${cached.fetchError}` };

  return {
    text: JSON.stringify(
      sortObject(json, buildOrderMap(cached.schema), true),
      null,
      2,
    ),
    status: 'Sorted using schema:',
    schemaUrl: url,
    error: '',
  };
});

const output = computed(() => result.value.text);
const highlightedInput = computed(() => highlightJson(input.value) + '\n');
const highlightedOutput = computed(() => highlightJson(output.value));

async function copyOutput() {
  await navigator.clipboard.writeText(output.value);
  copied.value = true;
  setTimeout(() => (copied.value = false), 1500);
}
</script>

<template>
  <div class="json-sorter">
    <div class="pane">
      <div class="pane-header">Input JSON</div>
      <div class="editor">
        <pre
          ref="inputLayer"
          class="pane-body highlight-layer"
          aria-hidden="true"><code v-html="highlightedInput" /></pre>
        <textarea
          v-model="input"
          class="pane-body input-layer"
          spellcheck="false"
          placeholder='Paste JSON here, e.g. { "$schema": "https://example.com/schema.json", ... }'
          @scroll="syncScroll" />
      </div>
    </div>

    <div class="pane">
      <div class="pane-header">
        Sorted result
        <button v-if="output" class="copy-btn" @click="copyOutput">
          {{ copied ? 'Copied!' : 'Copy' }}
        </button>
      </div>
      <pre class="pane-body"><code v-html="highlightedOutput" /></pre>
    </div>

    <p v-if="result.status" class="status">
      {{ result.status }}
      <a
        v-if="result.schemaUrl"
        :href="result.schemaUrl"
        target="_blank"
        rel="noopener"
        >{{ result.schemaUrl }}</a
      >
    </p>
    <p v-if="result.error" class="status status-error">{{ result.error }}</p>
  </div>
</template>

<style scoped>
.json-sorter {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin: 24px 0;
}

@media (max-width: 640px) {
  .json-sorter {
    grid-template-columns: 1fr;
  }
}

.pane {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--vp-c-divider);
  border-radius: 8px;
  overflow: hidden;
}

.pane-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  font-size: 13px;
  font-weight: 600;
  background: var(--vp-c-bg-soft);
  border-bottom: 1px solid var(--vp-c-divider);
}

.editor {
  position: relative;
  flex: 1;
  min-height: 400px;
}

.pane-body {
  position: absolute;
  inset: 0;
  margin: 0;
  padding: 12px;
  overflow: auto;
  white-space: pre-wrap;
  word-break: break-word;
  border: none;
  outline: none;
  resize: none;
  font-family: var(--vp-font-family-mono);
  font-size: 13px;
  line-height: 1.5;
  background: var(--vp-c-bg);
  color: var(--vp-c-text-1);
}

.pane > pre.pane-body {
  position: static;
  height: 400px;
}

.editor .highlight-layer {
  pointer-events: none;
}

.input-layer {
  background: transparent;
  color: transparent;
  caret-color: var(--vp-c-text-1);
}

.input-layer::selection {
  background: var(--vp-c-brand-soft);
}

:deep(.json-key) {
  color: var(--vp-c-brand-1);
}

:deep(.json-string) {
  color: var(--vp-c-danger-1);
}

:deep(.json-number) {
  color: var(--vp-c-warning-1);
}

:deep(.json-boolean),
:deep(.json-null) {
  color: var(--vp-c-purple-1, #916bbf);
}

.copy-btn {
  padding: 2px 10px;
  font-size: 12px;
  font-weight: 500;
  color: var(--vp-c-brand-1);
  background: var(--vp-c-brand-soft);
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.status {
  grid-column: 1 / -1;
  margin: 0;
  font-size: 13px;
  color: var(--vp-c-text-2);
}

.status-error {
  color: var(--vp-c-danger-1);
}
</style>
