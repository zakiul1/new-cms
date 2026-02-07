let editor = null;
let monacoReady = false;
let loaderLoading = false;
let bound = false;
let settingValue = false;

function guessLanguage(ext) {
    ext = (ext || "").toLowerCase();

    if (ext.endsWith("blade.php")) return "php";
    if (ext === "php") return "php";
    if (ext === "js") return "javascript";
    if (ext === "ts") return "typescript";
    if (ext === "json") return "json";
    if (ext === "css" || ext === "scss") return "css";
    if (ext === "html") return "html";
    if (ext === "md") return "markdown";
    return "plaintext";
}

function normalizePayload(payload) {
    // Livewire v3 sometimes sends event args as array
    if (Array.isArray(payload)) return payload[0] || {};
    return payload || {};
}

function loadAmdLoader() {
    return new Promise((resolve, reject) => {
        if (window.require && window.require.config) {
            resolve();
            return;
        }

        // loader already requested
        if (loaderLoading) {
            const t = setInterval(() => {
                if (window.require && window.require.config) {
                    clearInterval(t);
                    resolve();
                }
            }, 50);

            setTimeout(() => {
                clearInterval(t);
                reject(new Error("Monaco loader timeout"));
            }, 8000);

            return;
        }

        loaderLoading = true;

        const s = document.createElement("script");
        s.src = "/monaco/vs/loader.js";
        s.async = true;

        s.onload = () => resolve();
        s.onerror = () =>
            reject(new Error("Failed to load /monaco/vs/loader.js"));

        document.head.appendChild(s);
    });
}

function bootMonaco() {
    return loadAmdLoader().then(() => {
        if (monacoReady) return;

        window.require.config({ paths: { vs: "/monaco/vs" } });

        return new Promise((resolve, reject) => {
            window.require(
                ["vs/editor/editor.main"],
                () => {
                    monacoReady = true;
                    resolve();
                },
                reject,
            );
        });
    });
}

function getContainer() {
    return document.getElementById("plugin-monaco");
}

function getLivewireComponent(container) {
    const id = container?.dataset?.livewireId;
    if (!id) return null;
    return window.Livewire?.find(id) ?? null;
}

function setLivewireContent(container, value) {
    const component = getLivewireComponent(container);
    if (!component) return;

    component.set("data.content", value);
}

function markDirty(container, dirty) {
    const component = getLivewireComponent(container);
    if (!component) return;

    // optional: if you have PHP listener, otherwise harmless
    component.dispatch("monaco-dirty", { dirty: !!dirty });
}

function requestSave(container) {
    const component = getLivewireComponent(container);
    if (!component) return;

    // ✅ BEST: call PHP method directly (no event wiring needed)
    // You MUST have public function saveFile(): void in the Page (you do)
    component.call("saveFile");
}

function ensureEditor(content = "", ext = "", readOnly = true) {
    const container = getContainer();
    if (!container) return;

    bootMonaco()
        .then(() => {
            // If editor exists but container got replaced, recreate
            if (
                editor &&
                (!editor.getDomNode() || !editor.getDomNode().isConnected)
            ) {
                try {
                    editor.dispose();
                } catch (_) {}
                editor = null;
            }

            if (!editor) {
                editor = window.monaco.editor.create(container, {
                    value: content,
                    language: guessLanguage(ext),
                    automaticLayout: true,
                    theme: "vs",
                    readOnly,
                    minimap: { enabled: false },
                    fontSize: 13,
                    wordWrap: "on",
                });

                // Ctrl+S / Cmd+S => save
                editor.addCommand(
                    window.monaco.KeyMod.CtrlCmd | window.monaco.KeyCode.KeyS,
                    () => requestSave(container),
                );

                editor.onDidChangeModelContent(() => {
                    if (settingValue) return;
                    setLivewireContent(container, editor.getValue());
                    markDirty(container, true);
                });

                // new file load -> clean
                markDirty(container, false);
                return;
            }

            editor.updateOptions({ readOnly });

            const model = editor.getModel();
            if (model) {
                window.monaco.editor.setModelLanguage(
                    model,
                    guessLanguage(ext),
                );
            }

            // Update content without triggering dirty spam
            if (editor.getValue() !== content) {
                settingValue = true;
                editor.setValue(content);
                settingValue = false;
            }

            // New file loaded -> not dirty
            markDirty(container, false);
        })
        .catch((e) => console.error(e));
}

function bindLivewireEvents() {
    if (bound) return;
    bound = true;

    if (!window.Livewire?.on) return;

    // File/plugin changed from PHP -> update Monaco
    window.Livewire.on("monaco-set", (payload) => {
        payload = normalizePayload(payload);

        ensureEditor(
            String(payload.content ?? ""),
            String(payload.ext ?? ""),
            !!payload.readOnly,
        );
    });

    // Read-only toggle
    window.Livewire.on("monaco-readonly", (payload) => {
        payload = normalizePayload(payload);
        if (!editor) return;
        editor.updateOptions({ readOnly: !!payload.readOnly });
    });

    // After save -> clear dirty flag
    window.Livewire.on("monaco-saved", () => {
        const container = getContainer();
        if (!container) return;
        markDirty(container, false);
    });
}

function init() {
    bindLivewireEvents();

    const container = getContainer();
    if (!container) return;

    const component = getLivewireComponent(container);
    const content = component?.get?.("data.content") ?? "";
    const readOnly = container.dataset.readonly === "1";

    // ext will be set by monaco-set event from PHP, but initial load still renders
    ensureEditor(String(content), "", readOnly);
}

document.addEventListener("DOMContentLoaded", init);
document.addEventListener("livewire:navigated", init);

// OPTIONAL: if Filament/Livewire re-render without navigation
document.addEventListener("livewire:load", init);
