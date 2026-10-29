/**
 * Extract input fields from WhatsApp Flow JSON (builder flow_json or Meta meta_json).
 * Used for chatbot variable chips + interactive message form preview.
 *
 * @param {object|null|undefined} flowData
 * @returns {Array<{ name: string, label: string, type: string, required: boolean, screenId: string, screenTitle: string }>}
 */
export function extractWhatsappFlowFields(flowData) {
  if (!flowData || typeof flowData !== "object") {
    return [];
  }

  const screens = Array.isArray(flowData.screens) ? flowData.screens : [];
  const fields = [];
  const seen = new Set();

  const pushField = (raw, screen) => {
    if (!raw || typeof raw !== "object") {
      return;
    }

    const type = String(raw.type || "").trim();
    // Skip non-input / layout-only components
    if (
      !type ||
      [
        "Form",
        "Footer",
        "Image",
        "TextHeading",
        "TextSubheading",
        "TextBody",
        "TextCaption",
        "RichText",
        "EmbeddedLink",
        "ImageCarousel",
      ].includes(type)
    ) {
      return;
    }

    const name = String(raw.name || "").trim();
    if (!name || seen.has(name)) {
      return;
    }

    seen.add(name);
    fields.push({
      name,
      label: String(raw.label || raw.text || name).trim() || name,
      type,
      required: Boolean(raw.required),
      screenId: String(screen?.id || ""),
      screenTitle: String(screen?.title || screen?.id || "Screen"),
    });
  };

  const walkChildren = (nodes, screen) => {
    if (!Array.isArray(nodes)) {
      return;
    }

    nodes.forEach((node) => {
      if (!node || typeof node !== "object") {
        return;
      }

      pushField(node, screen);

      if (Array.isArray(node.children)) {
        walkChildren(node.children, screen);
      }
    });
  };

  screens.forEach((screen) => {
    if (!screen || typeof screen !== "object") {
      return;
    }

    // Builder format: screens[].fields[]
    if (Array.isArray(screen.fields)) {
      screen.fields.forEach((field) => pushField(field, screen));
    }

    // Meta format: screens[].layout.children[] (often Form > children)
    const layoutChildren = screen.layout?.children;
    walkChildren(layoutChildren, screen);

    // Some payloads nest form at screen.data / screen.content
    walkChildren(screen.children, screen);
  });

  return fields;
}

export function flowFieldVariableSyntax(name) {
  return `$(${String(name || "").trim()})`;
}
