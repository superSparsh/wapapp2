import React, { useMemo } from "react";
import {
  PlayCircleOutlined,
  ShoppingCartOutlined,
} from "@ant-design/icons";
import "../styles/template-message-preview.css";

const ASSETS = {
  phoneBg: "/images/templates/phone-bg.png",
  headerImage: "/images/templates/preview-header-image.png",
  divider: "/images/templates/message-divider.svg",
  linkIcon: "/images/templates/export.svg",
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

function PreviewButton({ icon, label }) {
  if (!label) {
    return null;
  }

  return (
    <div className="chatbot-template-preview__button">
      {icon ? (
        <img src={icon} alt="" className="chatbot-template-preview__button-icon" />
      ) : null}
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
}) => {
  const resolvedBody = bodyText?.trim() || "";
  const resolvedHeaderText = headerText?.trim() || "";
  const resolvedFooterText = footerText?.trim() || "";
  const resolvedButtonText = buttonText?.trim() || "";

  const showHeaderImage = headerType === "image" && uploadedMedia;
  const showHeaderVideo = headerType === "video" && uploadedMedia;
  const showHeaderText =
    (headerType === "text" && resolvedHeaderText) ||
    (interactiveType === "product_list" && resolvedHeaderText);

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

  const listSections = useMemo(
    () =>
      (sections || [])
        .map((section) => ({
          title: section.title?.trim() || "",
          rows: (section.rows || [])
            .map((row) => ({
              title: row.title?.trim() || "",
              description: row.description?.trim() || "",
            }))
            .filter((row) => row.title || row.description),
        }))
        .filter((section) => section.title || section.rows.length > 0),
    [sections]
  );

  const previewButtons = useMemo(() => {
    if (interactiveType === "list") {
      return resolvedButtonText ? (
        <PreviewButton icon={ASSETS.linkIcon} label={resolvedButtonText} />
      ) : null;
    }

    if (interactiveType === "button" && buttons.length > 0) {
      return buttons.map((btn, idx) => (
        <PreviewButton
          key={idx}
          icon={ASSETS.linkIcon}
          label={
            btn.type === "reply" ? btn.reply?.title || btn.title : "Button"
          }
        />
      ));
    }

    if (interactiveType === "cta_url" && resolvedButtonText) {
      return (
        <PreviewButton icon={ASSETS.linkIcon} label={resolvedButtonText} />
      );
    }

    if (interactiveType === "flow" && flow_cta?.trim()) {
      return <PreviewButton label={flow_cta.trim()} />;
    }

    if (
      interactiveType === "product" ||
      interactiveType === "product_list" ||
      interactiveType === "catalog_message"
    ) {
      return (
        <div className="chatbot-template-preview__button">
          <ShoppingCartOutlined className="chatbot-template-preview__button-anticon" />
          <span>View Items</span>
        </div>
      );
    }

    return null;
  }, [
    interactiveType,
    resolvedButtonText,
    buttons,
    flow_cta,
  ]);

  const hasContent =
    showHeaderImage ||
    showHeaderVideo ||
    showHeaderText ||
    resolvedBody ||
    resolvedFooterText ||
    previewButtons ||
    listSections.length > 0;

  return (
    <div className="chatbot-template-preview">
      <div className="chatbot-template-preview__intro">
        <h3 className="chatbot-template-preview__title">Message Preview</h3>
        <p className="chatbot-template-preview__subtitle">
          Template preview message look like
        </p>
      </div>

      <div className="chatbot-template-preview__phone">
        <div className="chatbot-template-preview__phone-shell">
          <img
            src={ASSETS.phoneBg}
            alt=""
            className="chatbot-template-preview__phone-bg"
          />
          <span className="chatbot-template-preview__phone-header-name" title={resolvePreviewBusinessName()}>
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

                  {interactiveType === "list" && listSections.length > 0 ? (
                    <div className="chatbot-template-preview__sections">
                      {listSections.map((section, sectionIndex) => (
                        <div
                          key={`preview-section-${sectionIndex}`}
                          className="chatbot-template-preview__section"
                        >
                          {section.title ? (
                            <p className="chatbot-template-preview__section-title">
                              {section.title}
                            </p>
                          ) : null}
                          {section.rows.map((row, rowIndex) => (
                            <div
                              key={`preview-row-${sectionIndex}-${rowIndex}`}
                              className="chatbot-template-preview__section-row"
                            >
                              <span className="chatbot-template-preview__section-row-title">
                                {row.title || "Row title"}
                              </span>
                              {row.description ? (
                                <span className="chatbot-template-preview__section-row-description">
                                  {row.description}
                                </span>
                              ) : null}
                            </div>
                          ))}
                        </div>
                      ))}
                    </div>
                  ) : null}

                  {previewButtons ? (
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
          </div>
        </div>
      </div>
    </div>
  );
};

export default InteractiveMessagePreview;
