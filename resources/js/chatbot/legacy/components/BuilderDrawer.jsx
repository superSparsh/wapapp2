import React, { useEffect } from "react";
import { ConfigProvider, Drawer } from "antd";

export const builderDrawerStyles = {
  header: {
    borderBottom: "1px solid var(--color-divider, #e5e7eb)",
    padding: "14px 20px",
    background: "var(--color-elevated, #fff)",
  },
  body: {
    flex: 1,
    overflowY: "auto",
    overflowX: "hidden",
    padding: "20px",
    paddingBottom: "24px",
    background: "var(--color-surface, #f7f8fa)",
  },
  footer: {
    borderTop: "1px solid var(--color-divider, #e5e7eb)",
    padding: "12px 20px",
    background: "var(--color-elevated, #fff)",
  },
  wrapper: {
    zIndex: 1100,
  },
  mask: {
    zIndex: 1100,
  },
};

export function BuilderDrawerTitle({ icon, children }) {
  return (
    <div className="chatbot-builder-drawer__title">
      {icon ? (
        <span className="chatbot-builder-drawer__title-icon" aria-hidden="true">
          {icon}
        </span>
      ) : null}
      <span className="chatbot-builder-drawer__title-text">{children}</span>
    </div>
  );
}

export default function BuilderDrawer({
  title,
  open,
  onClose,
  width = 420,
  footer,
  children,
  className = "",
  ...rest
}) {
  useEffect(() => {
    if (!open) {
      return undefined;
    }

    document.body.classList.add("chatbot-builder-drawer-open");

    // Hard guarantee: any select popup above drawer while open.
    const style = document.createElement("style");
    style.setAttribute("data-chatbot-drawer-select-fix", "true");
    style.textContent = `
      body.chatbot-builder-drawer-open .ant-select-dropdown,
      body.chatbot-builder-drawer-open .ant-picker-dropdown,
      body.chatbot-builder-drawer-open .ant-cascader-dropdown,
      body.chatbot-builder-drawer-open .ant-dropdown,
      .chatbot-builder-select-dropdown {
        z-index: 10050 !important;
      }
      .chatbot-builder-drawer-root,
      .chatbot-builder-drawer-root .ant-drawer-mask,
      .chatbot-builder-drawer-root .ant-drawer-wrap {
        z-index: 1100 !important;
      }
    `;
    document.head.appendChild(style);

    return () => {
      document.body.classList.remove("chatbot-builder-drawer-open");
      style.remove();
    };
  }, [open]);

  return (
    <ConfigProvider
      getPopupContainer={() => document.body}
      theme={{
        token: {
          zIndexPopupBase: 10050,
        },
      }}
    >
      <Drawer
        className={`chatbot-builder-drawer ${className}`.trim()}
        rootClassName="chatbot-builder-drawer-root"
        placement="right"
        width={width}
        open={open}
        onClose={onClose}
        styles={builderDrawerStyles}
        title={title}
        footer={footer}
        zIndex={1100}
        destroyOnClose={false}
        {...rest}
      >
        {children}
      </Drawer>
    </ConfigProvider>
  );
}
