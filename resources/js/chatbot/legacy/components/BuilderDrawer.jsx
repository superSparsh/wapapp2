import React from "react";
import { Drawer } from "antd";

export const builderDrawerStyles = {
  header: {
    borderBottom: "1px solid var(--color-divider, #e5e7eb)",
    padding: "14px 20px",
    background: "var(--color-elevated, #fff)",
  },
  body: {
    flex: 1,
    overflowY: "auto",
    padding: "20px",
    paddingBottom: "24px",
    background: "var(--color-surface, #f7f8fa)",
  },
  footer: {
    borderTop: "1px solid var(--color-divider, #e5e7eb)",
    padding: "12px 20px",
    background: "var(--color-elevated, #fff)",
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
  return (
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
      {...rest}
    >
      {children}
    </Drawer>
  );
}
