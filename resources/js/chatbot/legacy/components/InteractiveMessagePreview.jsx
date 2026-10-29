import React, { useMemo } from "react";
import {
  PlayCircleOutlined,
  ShoppingCartOutlined,
  UnorderedListOutlined,
} from "@ant-design/icons";
import "../styles/template-message-preview.css";
import { extractWhatsappFlowFields } from "./extractWhatsappFlowFields";

const ASSETS = {
  phoneBg: "/images/templates/phone-bg.png",
  headerImage: "/images/templates/preview-header-image.png",
  divider: "/images/templates/message-divider.svg",
  linkIcon: "/images/templates/export.svg",
  replyIcon: "/images/templates/quick-reply.svg",
  flowIcon: "/images/templates/flow.svg",
};

function resolvePreviewBusinessName() {
  if (typeof document === "undefined") {
    return "Your Business";
  }

  const fromMeta = document
    .querySelector('meta[name="preview-business-name"]')
    ?.getAttribute("content")
    ?.trim();

  return fromMeta || "Your Business";
}

function buttonLabel(btn) {
  if (!btn || typeof btn !== "object") {
    return "";
  }

  return (
    btn.reply?.title ||
    btn.title ||
    btn.text ||
    btn.label ||
    ""
  ).trim();
}

function fieldTypeHint(type) {
  const t = String(type || "").toLowerCase();
  if (t.includes("date")) return "Date";
  if (t.includes("dropdown") || t.includes("radio") || t.includes("checkbox")) {
    return "Choice";
  }
  if (t.includes("textarea") || t.includes("paragraph")) return "Long text";
  if (t.includes("email")) return "Email";
  if (t.includes("phone")) return "Phone";
  if (t.includes("number")) return "Number";
  if (t.includes("optin") || t.includes("switch")) return "Toggle";
  return "Text";
}

function PreviewButton({ icon, label, anticon = null }) {
  if (!label) {
    return null;
  }

  return (
    <div className="chatbot-template-preview__button">
      {anticon ||
        (icon ? (
          <img src={icon} alt="" className="chatbot-template-preview__button-icon" />
        ) : null)}
      <span>{label}</span>
    </div>
  );
}

const InteractiveMessagePreview = ({
  interactiveType,
  headerType,
  headerText,
  bodyText,
  footerText,
  buttons = [],
  sections = [],
  uploadedMedia,
  flow_cta,
  buttonText,
  selectedFlowData = null,
}) => {
  const resolvedBody = bodyText?.trim() || "";
  const resolvedHeaderText = headerText?.trim() || "";
  const resolvedFooterText = footerText?.trim() || "";
  const resolvedButtonText = buttonText?.trim() || "";
  const type = String(interactiveType || "button").toLowerCase();

  const showHeaderImage = headerType === "image" && Boolean(uploadedMedia);
  const showHeaderVideo = headerType === "video" && Boolean(uploadedMedia);
  const showHeaderText =
    (headerType === "text" && resolvedHeaderText) ||
    (type === "product_list" && resolvedHeaderText);

  const flowFields = useMemo(
    () => (type === "flow" ? extractWhatsappFlowFields(selectedFlowData) : []),
    [type, selectedFlowData]
  );

  const headerImageSrc = useMemo(() => {
    if (!uploadedMedia) {
      return ASSETS.headerImage;
    }

    if (typeof uploadedMedia === "string") {
      return uploadedMedia;
    }

    if (uploadedMedia.thumbnail_path) {
      return uploadedMedia.thumbnail_path;
    }

    if (
      uploadedMedia instanceof File &&
      uploadedMedia.type?.startsWith?.("image/")
    ) {
      return URL.createObjectURL(uploadedMedia);
    }

    return ASSETS.headerImage;
  }, [uploadedMedia]);

  const listRowCount = useMemo(
    () =>
      (sections || []).reduce((count, section) => {
        const rows = Array.isArray(section?.rows) ? section.rows : [];
        return (
          count +
          rows.filter((row) => (row?.title || row?.description || "").trim()).length
        );
      }, 0),
    [sections]
  );

  const previewButtons = useMemo(() => {
    if (type === "list") {
      return (
        <PreviewButton
          anticon={
            <UnorderedListOutlined className="chatbot-template-preview__button-anticon" />
          }
          label={resolvedButtonText || "View options"}
        />
      );
    }

    if (type === "button" && buttons.length > 0) {
      return buttons
        .map((btn, idx) => {
          const label = buttonLabel(btn);
          if (!label) {
            return null;
          }

          return (
            <PreviewButton
              key={btn.id || idx}
              icon={ASSETS.replyIcon}
              label={label}
            />
          );
        })
        .filter(Boolean);
    }

    if (type === "cta_url" && resolvedButtonText) {
      return (
        <PreviewButton icon={ASSETS.linkIcon} label={resolvedButtonText} />
      );
    }

    if (type === "flow") {
      return (
        <PreviewButton
          icon={ASSETS.flowIcon}
          label={(flow_cta || "").trim() || "Open"}
        />
      );
    }

    if (
      type === "product" ||
      type === "product_list" ||
      type === "catalog_message"
    ) {
      return (
        <PreviewButton
          anticon={
            <ShoppingCartOutlined className="chatbot-template-preview__button-anticon" />
          }
          label="View items"
        />
      );
    }

    return null;
  }, [type, resolvedButtonText, buttons, flow_cta]);

  const hasButtons =
    previewButtons != null &&
    (Array.isArray(previewButtons) ? previewButtons.length > 0 : true);

  const hasContent =
    showHeaderImage ||
    showHeaderVideo ||
    showHeaderText ||
    resolvedBody ||
    resolvedFooterText ||
    hasButtons;

  return (
    <div className="chatbot-template-preview">
      <div className="chatbot-template-preview__intro">
        <h3 className="chatbot-template-preview__title">Message Preview</h3>
        <p className="chatbot-template-preview__subtitle">
          {type === "flow"
            ? "Chat bubble + form fields from the selected WhatsApp Flow"
            : "How this interactive message will look on WhatsApp"}
        </p>
      </div>

      <div className="chatbot-template-preview__phone">
        <div className="chatbot-template-preview__phone-shell">
          <div className="chatbot-template-preview__phone-bezel" aria-hidden="true" />
          <div className="chatbot-template-preview__phone-screen">
            <img
              src={ASSETS.phoneBg}
              alt=""
              className="chatbot-template-preview__phone-bg"
            />
            <span
              className="chatbot-template-preview__phone-header-name"
              title={resolvePreviewBusinessName()}
            >
              {resolvePreviewBusinessName()}
            </span>
            <div className="chatbot-template-preview__phone-content">
              <div className="chatbot-template-preview__bubble">
                {!hasContent ? (
                  <p className="chatbot-template-preview__placeholder">
                    Start typing to see your message preview here.
                  </p>
                ) : (
                  <>
                    {showHeaderImage ? (
                      <img
                        src={headerImageSrc}
                        alt=""
                        className="chatbot-template-preview__header-image"
                      />
                    ) : null}

                    {showHeaderVideo ? (
                      <div className="chatbot-template-preview__header-video">
                        <PlayCircleOutlined />
                      </div>
                    ) : null}

                    {showHeaderText ? (
                      <p className="chatbot-template-preview__header-text">
                        {resolvedHeaderText}
                      </p>
                    ) : null}

                    {resolvedBody ? (
                      <div className="chatbot-template-preview__body">
                        {resolvedBody}
                      </div>
                    ) : null}

                    {resolvedFooterText ? (
                      <p className="chatbot-template-preview__footer">
                        {resolvedFooterText}
                      </p>
                    ) : null}

                    {type === "list" && listRowCount > 0 ? (
                      <p className="chatbot-template-preview__list-hint">
                        {listRowCount} list item{listRowCount === 1 ? "" : "s"} open
                        when the button is tapped
                      </p>
                    ) : null}

                    {hasButtons ? (
                      <>
                        <img
                          src={ASSETS.divider}
                          alt=""
                          className="chatbot-template-preview__divider"
                        />
                        <div className="chatbot-template-preview__buttons">
                          {previewButtons}
                        </div>
                      </>
                    ) : null}
                  </>
                )}
              </div>

              {type === "flow" && flowFields.length > 0 ? (
                <div className="chatbot-template-preview__flow-form">
                  <p className="chatbot-template-preview__flow-form-title">
                    Form preview
                  </p>
                  {flowFields.map((field) => (
                    <div
                      key={field.name}
                      className="chatbot-template-preview__flow-field"
                    >
                      <div className="chatbot-template-preview__flow-field-label">
                        <span>
                          {field.label}
                          {field.required ? " *" : ""}
                        </span>
                        <span className="chatbot-template-preview__flow-field-type">
                          {fieldTypeHint(field.type)}
                        </span>
                      </div>
                      <div className="chatbot-template-preview__flow-field-input">
                        {fieldTypeHint(field.type) === "Choice"
                          ? "Select…"
                          : fieldTypeHint(field.type) === "Date"
                            ? "DD/MM/YYYY"
                            : "Enter value"}
                      </div>
                      <code className="chatbot-template-preview__flow-field-var">
                        $({field.name})
                      </code>
                    </div>
                  ))}
                </div>
              ) : null}

              {type === "flow" && selectedFlowData && flowFields.length === 0 ? (
                <p className="chatbot-template-preview__list-hint" style={{ marginTop: 8 }}>
                  Selected flow has no named input fields to preview.
                </p>
              ) : null}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default InteractiveMessagePreview;
