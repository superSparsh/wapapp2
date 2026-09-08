import React, { useState, useCallback, useEffect, useRef } from "react";
import ReactFlow, {
  addEdge,
  useNodesState,
  useEdgesState,
  Controls,
  Background,
  MiniMap,
  Panel,
  Handle,
  Position,
  getBezierPath,
  BaseEdge,
  getSmoothStepPath,
  ConnectionMode,
  MarkerType,
  useNodesInitialized,
  useUpdateNodeInternals,
} from "reactflow";
import "reactflow/dist/style.css";
import "./styles/ChatBotFlow.css";

// Custom CSS for branching edges and handles
const customStyles = `
  .react-flow__handle {
    transition: all 0.2s ease;
  }
  
  .react-flow__handle:hover {
    transform: scale(1.2);
    box-shadow: 0 2px 4px rgba(0,0,0,0.3) !important;
  }
  
  .quick-reply-handle {
    background: #52c41a !important;
    border: 2px solid white !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3) !important;
    cursor: crosshair !important;
  }
  
  .default-handle {
    background: #faad14 !important;
    border: 2px solid white !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3) !important;
    cursor: crosshair !important;
  }
  
  .edgebutton-foreignobject {
    pointer-events: none;
  }
  
  .edgebutton-foreignobject div {
    pointer-events: all;
    user-select: none;
  }
`;

import {
  Button,
  Space,
  message,
  Tag,
  Image,
} from "antd";
import {
  MessageOutlined,
  BranchesOutlined,
  ClockCircleOutlined,
  ApiOutlined,
  FunctionOutlined,
  CalendarOutlined,
  RobotOutlined,
  HomeOutlined,
  EyeOutlined,
  UploadOutlined,
  QuestionCircleOutlined,
  FileTextOutlined,
  LoadingOutlined,
  ThunderboltOutlined,
  CodeOutlined,
  CarOutlined,
  DeleteOutlined,
  PlusOutlined,
} from "@ant-design/icons";

import axios from "axios";
import { getBuilderConfig } from "./api.js";
import {
  alertChatbotAction,
  confirmChatbotAction,
  requestConnectionDelete,
  requestNodeDelete,
} from "./confirm.js";
import ReactFlowWelcomeMessageModule from "./components/ReactFlowWelcomeMessageModule.jsx";
import ReactFlowTypingIndicatorModule from "./components/ReactFlowTypingIndicatorModule.jsx";
import ReactFlowDelayModule from "./components/ReactFlowDelayModule.jsx";
import ReactFlowEnhancedConditionModule from "./components/ReactFlowEnhancedConditionModule.jsx";
import ReactFlowHttpRequestModule from "./components/ReactFlowHttpRequestModule.jsx";
import ReactFlowJumpToStepModule from "./components/ReactFlowJumpToStepModule.jsx";
import ReactFlowDateTimeConditionModule from "./components/ReactFlowDateTimeConditionModule.jsx";
import ReactFlowFunctionCallModule from "./components/ReactFlowFunctionCallModule.jsx";
import ReactFlowTemplateMessageModule from "./components/ReactFlowTemplateMessageModule.jsx";
import ReactFlowInteractiveMessageModule from "./components/ReactFlowInteractiveMessageModule.jsx";
import ReactFlowCarouselTemplateModule from "./components/ReactFlowCarouselTemplateModule.jsx";
import ReactFlowWhatsappFlowTemplateModule from "./components/ReactFlowWhatsappFlowTemplateModule.jsx";
import ReactFlowWaitForResponseModule from "./components/ReactFlowWaitForResponseModule.jsx";
import ReactFlowNaturalLanguageModule from "./components/ReactFlowNaturalLanguageModule.jsx";
import BuilderDrawer, { BuilderDrawerTitle } from "./components/BuilderDrawer.jsx";
import AIChatbotGenerator from "./components/AIChatbotGenerator.jsx";
import ReactFlowMediaModule from "./components/ReactFlowMediaModule.jsx";
import VisibleChatbotEdge from "./components/VisibleChatbotEdge.jsx";

/** Hidden delivery-status branches register ghost handles and break edge rendering. */
const SHOW_LEGACY_DELIVERY_HANDLES = false;

const renderCategoryLabel = (label) => {
  if (typeof label === "string" || typeof label === "number") {
    return label;
  }

  return label;
};

const decorateEdge = (edge) => {
  const strokeColor = edge.style?.stroke || "#22c55e";
  return {
    ...edge,
    type: edge.type || "smoothstep",
    zIndex: edge.zIndex ?? 1000,
    style: {
      stroke: strokeColor,
      strokeWidth: 2.5,
      ...(edge.style || {}),
    },
    markerEnd: edge.markerEnd || {
      type: MarkerType.ArrowClosed,
      color: strokeColor,
      width: 18,
      height: 18,
    },
  };
};

const NodeCategoryToolbar = ({ categories }) => {
  const [openKey, setOpenKey] = useState(null);
  const barRef = useRef(null);

  useEffect(() => {
    const handlePointerDown = (event) => {
      if (!barRef.current?.contains(event.target)) {
        setOpenKey(null);
      }
    };

    document.addEventListener("mousedown", handlePointerDown);

    return () => {
      document.removeEventListener("mousedown", handlePointerDown);
    };
  }, []);

  return (
    <div className="chatbot-category-bar" ref={barRef}>
      {categories.map((category) => {
        const isOpen = openKey === category.key;

        return (
          <div key={category.key} className="chatbot-category-dropdown">
            <button
              type="button"
              className={`chatbot-category-trigger${isOpen ? " chatbot-category-trigger--open" : ""}`}
              aria-expanded={isOpen}
              onClick={() => setOpenKey(isOpen ? null : category.key)}
            >
              <span className="chatbot-category-trigger__icon">{category.icon}</span>
              <span>{category.label}</span>
              <svg
                className="chatbot-category-trigger__chevron"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                aria-hidden="true"
              >
                <path d="m6 9 6 6 6-6" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
            </button>

            {isOpen && (
              <div className="chatbot-category-menu" role="menu">
                {category.children.map((child) => (
                  <div
                    key={child.key}
                    className="chatbot-category-menu__item"
                    role="menuitem"
                    draggable={child.draggable !== false}
                    onDragStart={(event) => {
                      child.onDragStart?.(event);
                    }}
                    onDragEnd={() => setOpenKey(null)}
                    style={child.style}
                  >
                    <span className="chatbot-category-menu__icon">{child.icon}</span>
                    <span className="chatbot-category-menu__label">
                      {renderCategoryLabel(child.label)}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
};

// Minimal Node Components
// Enhanced Welcome Message Node with Quick Reply Support
const WelcomeMessageNode = ({ data = {}, selected, id }) => {
  const quickReplies = data.quickReplies || [];

  // Debug logging
  console.log("WelcomeMessageNode data:", data);
  console.log("Quick replies:", quickReplies);
  console.log("Selected template:", data.selectedTemplate);

  return (
    <div
      className="chatbot-node-card"
      style={{
        position: "relative",
      }}
    >
      <Handle type="target" position={Position.Top} id="default" />

      <div className="chatbot-node-card__header">
        <MessageOutlined />
        <span>{data.label || "Welcome Message"}</span>
        <button
          type="button"
          className="chatbot-node-card__delete"
          onClick={(e) => {
            e.stopPropagation();
            requestNodeDelete(id);
          }}
          title="Delete Node"
        >
          <DeleteOutlined />
        </button>
      </div>

      <div className="chatbot-node-card__body">
        {data.messageType === "template" && data.selectedTemplate && (
          <div>Template: {data.selectedTemplate.template_name}</div>
        )}
        {data.messageType === "text" && data.triggerKeyword && (
          <div>Trigger: {data.triggerKeyword}</div>
        )}
        {data.messageType === "text" && data.welcomeMessage && (
          <div>{data.welcomeMessage.substring(0, 80)}{data.welcomeMessage.length > 80 ? "..." : ""}</div>
        )}
        {data.messageType === "text" && data.text && !data.triggerKeyword && (
          <div>{data.text.substring(0, 80)}{data.text.length > 80 ? "..." : ""}</div>
        )}
        {data.selectedTemplate &&
          (data.selectedTemplate.button_type == 2 ||
            data.selectedTemplate.button_type == 4) && (
          <div>Quick Reply Template</div>
        )}
      </div>

      {/* Multiple output handles for quick replies */}
      {(quickReplies.length > 0 ||
        (data.selectedTemplate &&
          (data.selectedTemplate.button_type == 2 ||
            data.selectedTemplate.button_type == 4))) && (
          <div
            style={{
              marginTop: 8,
              position: "relative",
              borderTop: "1px solid #f0f0f0",
              paddingTop: 8,
            }}
          >
            {/* Quick Reply Section Title */}
            <div
              style={{
                fontSize: "10px",
                color: "#666",
                fontWeight: "500",
                marginBottom: 6,
                textAlign: "center",
              }}
            >
              Quick Reply Branches
            </div>

            {/* Quick Reply Handles - Enhanced Layout */}
            <div
              style={{
                display: "flex",
                flexDirection: "column",
                gap: "6px",
                marginBottom: 8,
              }}
            >
              {quickReplies.length > 0
                ? quickReplies.map((reply, index) => (
                  <div
                    key={`reply-${index}`}
                    style={{
                      display: "flex",
                      alignItems: "center",
                      background:
                        "linear-gradient(135deg, #f6ffed 0%, #d9f7be 100%)",
                      border: "1px solid #b7eb8f",
                      borderRadius: "6px",
                      padding: "6px 8px 6px 8px",
                      paddingRight: "20px",
                      margin: "0",
                      position: "relative",
                      transition: "all 0.2s ease",
                      cursor: "pointer",
                      boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
                    }}
                    title={reply.text} // Tooltip for full text
                    onMouseEnter={(e) => {
                      e.currentTarget.style.transform = "translateX(2px)";
                      e.currentTarget.style.boxShadow =
                        "0 2px 6px rgba(0,0,0,0.15)";
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.transform = "translateX(0)";
                      e.currentTarget.style.boxShadow =
                        "0 1px 3px rgba(0,0,0,0.1)";
                    }}
                  >
                    <div
                      style={{
                        display: "flex",
                        alignItems: "center",
                        flex: 1,
                      }}
                    >
                      <div
                        style={{
                          width: "6px",
                          height: "6px",
                          borderRadius: "50%",
                          background: "#52c41a",
                          marginRight: "8px",
                          flexShrink: 0,
                        }}
                      />
                      <span
                        style={{
                          fontSize: "10px",
                          color: "#237804",
                          fontWeight: "600",
                          lineHeight: "1.2",
                        }}
                      >
                        {reply.text}
                      </span>
                    </div>
                    <Handle
                      type="source"
                      position={Position.Right}
                      id={`reply-${index}`}
                      className="quick-reply-handle"
                      style={{
                        width: 12,
                        height: 12,
                        border: "2px solid white",
                        boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
                      }}
                    />
                  </div>
                ))
                : // Show placeholder handles for template quick replies
                Array.from({ length: 3 }, (_, index) => (
                  <div
                    key={`placeholder-${index}`}
                    style={{
                      display: "flex",
                      alignItems: "center",
                      background:
                        "linear-gradient(135deg, #f6ffed 0%, #d9f7be 100%)",
                      border: "1px solid #b7eb8f",
                      borderRadius: "6px",
                      padding: "6px 8px 6px 8px",
                      paddingRight: "20px",
                      margin: "0",
                      position: "relative",
                      transition: "all 0.2s ease",
                      cursor: "pointer",
                      boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
                    }}
                    title={`Quick Reply ${index + 1}`}
                    onMouseEnter={(e) => {
                      e.currentTarget.style.transform = "translateX(2px)";
                      e.currentTarget.style.boxShadow =
                        "0 2px 6px rgba(0,0,0,0.15)";
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.transform = "translateX(0)";
                      e.currentTarget.style.boxShadow =
                        "0 1px 3px rgba(0,0,0,0.1)";
                    }}
                  >
                    <div
                      style={{
                        display: "flex",
                        alignItems: "center",
                        flex: 1,
                      }}
                    >
                      <div
                        style={{
                          width: "6px",
                          height: "6px",
                          borderRadius: "50%",
                          background: "#52c41a",
                          marginRight: "8px",
                          flexShrink: 0,
                        }}
                      />
                      <span
                        style={{
                          fontSize: "10px",
                          color: "#237804",
                          fontWeight: "600",
                          lineHeight: "1.2",
                        }}
                      >
                        Quick Reply {index + 1}
                      </span>
                    </div>
                    <Handle
                      type="source"
                      position={Position.Right}
                      id={`reply-${index}`}
                      className="quick-reply-handle"
                      style={{
                        width: 12,
                        height: 12,
                        border: "2px solid white",
                        boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
                      }}
                    />
                  </div>
                ))}
            </div>
          </div>
        )}

      {/* Delivery Status Handles (hidden for Welcome Message node) */}
      {SHOW_LEGACY_DELIVERY_HANDLES && (
      <div
        style={{
          marginTop: 8,
          position: "relative",
          borderTop: "1px solid #f0f0f0",
          paddingTop: 8,
        }}
      >
        {/* Delivery Status Section Title */}
        <div
          style={{
            fontSize: "10px",
            color: "#666",
            fontWeight: "500",
            marginBottom: 6,
            textAlign: "center",
          }}
        >
          Delivery Status
        </div>

        <div
          style={{
            display: "flex",
            flexDirection: "column",
            gap: "4px",
          }}
        >
          {/* Unread Branch */}
          <div
            style={{
              display: "flex",
              alignItems: "center",
              background: "linear-gradient(135deg, #fff2e8 0%, #ffd8bf 100%)",
              border: "1px solid #ffbb96",
              borderRadius: "6px",
              padding: "6px 8px 6px 8px",
              paddingRight: "20px",
              margin: "0",
              position: "relative",
              transition: "all 0.2s ease",
              cursor: "pointer",
              boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.transform = "translateX(2px)";
              e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.transform = "translateX(0)";
              e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                flex: 1,
              }}
            >
              <div
                style={{
                  width: "6px",
                  height: "6px",
                  borderRadius: "50%",
                  background: "#fa8c16",
                  marginRight: "8px",
                  flexShrink: 0,
                }}
              />
              <span
                style={{
                  fontSize: "10px",
                  color: "#d46b08",
                  fontWeight: "600",
                  lineHeight: "1.2",
                }}
              >
                Unread
              </span>
            </div>
            <Handle
              type="source"
              position={Position.Right}
              id="unread"
              className="default-handle"
              style={{
                width: 12,
                height: 12,
                marginLeft: 8,
                border: "2px solid white",
                boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
              }}
            />
          </div>

          {/* Undelivered Branch */}
          <div
            style={{
              display: "flex",
              alignItems: "center",
              background: "linear-gradient(135deg, #fff1f0 0%, #ffccc7 100%)",
              border: "1px solid #ffa39e",
              borderRadius: "6px",
              padding: "6px 8px 6px 8px",
              paddingRight: "20px",
              margin: "0",
              position: "relative",
              transition: "all 0.2s ease",
              cursor: "pointer",
              boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.transform = "translateX(2px)";
              e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.transform = "translateX(0)";
              e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                flex: 1,
              }}
            >
              <div
                style={{
                  width: "6px",
                  height: "6px",
                  borderRadius: "50%",
                  background: "#f5222d",
                  marginRight: "8px",
                  flexShrink: 0,
                }}
              />
              <span
                style={{
                  fontSize: "10px",
                  color: "#cf1322",
                  fontWeight: "600",
                  lineHeight: "1.2",
                }}
              >
                Undelivered
              </span>
            </div>
            <Handle
              type="source"
              position={Position.Right}
              id="undelivered"
              className="default-handle"
              style={{
                width: 12,
                height: 12,
                marginLeft: 8,
                border: "2px solid white",
                boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
              }}
            />
          </div>
        </div>
      </div>
      )}

      {/* Default output handle when no quick replies exist */}
      {!quickReplies.length &&
        (!data.selectedTemplate ||
          (data.selectedTemplate.button_type != 2 &&
            data.selectedTemplate.button_type != 4)) && (
          <div
            style={{
              marginTop: 8,
              position: "relative",
              borderTop: "1px solid #f0f0f0",
              paddingTop: 8,
            }}
          >
            <div
              style={{
                fontSize: "10px",
                color: "#666",
                fontWeight: "500",
                marginBottom: 6,
                textAlign: "center",
              }}
            >
              Continue Flow
            </div>
            <div
              style={{
                display: "flex",
                alignItems: "center",
                background: "linear-gradient(135deg, #e6f7ff 0%, #bae7ff 100%)",
                border: "1px solid #91d5ff",
                borderRadius: "6px",
                padding: "6px 8px 6px 8px",
                paddingRight: "20px",
                margin: "0",
                position: "relative",
                transition: "all 0.2s ease",
                cursor: "pointer",
                boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.transform = "translateX(2px)";
                e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.transform = "translateX(0)";
                e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
              }}
            >
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  flex: 1,
                }}
              >
                <div
                  style={{
                    width: "6px",
                    height: "6px",
                    borderRadius: "50%",
                    background: "#1890ff",
                    marginRight: "8px",
                    flexShrink: 0,
                  }}
                />
                <span
                  style={{
                    fontSize: "10px",
                    color: "#096dd9",
                    fontWeight: "600",
                    lineHeight: "1.2",
                  }}
                >
                  Next Node
                </span>
              </div>
              <Handle
                type="source"
                position={Position.Right}
                id="default"
                className="default-handle"
                style={{
                  width: 12,
                  height: 12,
                  marginLeft: 8,
                  border: "2px solid white",
                  boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
                }}
              />
            </div>
          </div>
        )}
    </div>
  );
};

const TypingIndicatorNode = ({ data = {}, selected, id }) => {
  console.log("TypingIndicatorNode rendered:", { id, data, selected });

  return (
    <div
      style={{
        padding: 10,
        background: "white",
        border: selected ? "2px solid #1890ff" : "1px solid #ccc",
        borderRadius: 8,
        minWidth: 150,
        maxWidth: 200,
        position: "relative",
      }}
    >
      <Handle type="target" position={Position.Top} id="default" />

      {/* Delete Button */}
      <div
        style={{
          position: "absolute",
          top: "-8px",
          right: "-8px",
          background: "#ff4d4f",
          color: "white",
          border: "none",
          borderRadius: "50%",
          width: "20px",
          height: "20px",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          cursor: "pointer",
          fontSize: "12px",
          zIndex: 1000,
          opacity: selected ? 1 : 0.7,
          transition: "all 0.2s ease",
        }}
        onClick={(e) => {
          e.stopPropagation();
            requestNodeDelete(id);
        }}
        onMouseEnter={(e) => {
          e.currentTarget.style.transform = "scale(1.1)";
          e.currentTarget.style.background = "#ff7875";
        }}
        onMouseLeave={(e) => {
          e.currentTarget.style.transform = "scale(1)";
          e.currentTarget.style.background = "#ff4d4f";
        }}
        title="Delete Node"
      >
        <DeleteOutlined style={{ fontSize: "10px" }} />
      </div>

      <div style={{ fontWeight: "bold", marginBottom: 5 }}>
        Typing Indicator
      </div>
      <div style={{ fontSize: "12px", color: "#666" }}>
        <div>Duration: {data.duration || 3}s</div>
      </div>

      {/* Output Handle - Simple and visible */}
      <Handle
        type="source"
        position={Position.Bottom}
        id="default"
        style={{
          background: "#52c41a",
          border: "2px solid white",
          width: "12px",
          height: "12px",
          cursor: "crosshair",
        }}
      />
    </div>
  );
};

const EnhancedConditionNode = ({ data = {}, selected, id }) => (
  <div
    style={{
      padding: 10,
      background: "white",
      border: selected ? "2px solid #1890ff" : "1px solid #ccc",
      borderRadius: 8,
      minWidth: 150,
      maxWidth: 200,
      position: "relative",
    }}
  >
    <Handle type="target" position={Position.Top} id="default" />

    {/* Delete Button */}
    <div
      style={{
        position: "absolute",
        top: "-8px",
        right: "-8px",
        background: "#ff4d4f",
        color: "white",
        border: "none",
        borderRadius: "50%",
        width: "20px",
        height: "20px",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        cursor: "pointer",
        fontSize: "12px",
        zIndex: 1000,
        opacity: selected ? 1 : 0.7,
        transition: "all 0.2s ease",
      }}
      onClick={(e) => {
        e.stopPropagation();
            requestNodeDelete(id);
      }}
      onMouseEnter={(e) => {
        e.currentTarget.style.transform = "scale(1.1)";
        e.currentTarget.style.background = "#ff7875";
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.transform = "scale(1)";
        e.currentTarget.style.background = "#ff4d4f";
      }}
      title="Delete Node"
    >
      <DeleteOutlined style={{ fontSize: "10px" }} />
    </div>

    <div style={{ fontWeight: "bold", marginBottom: 5 }}>
      Enhanced Condition
    </div>
    <div style={{ fontSize: "12px", color: "#666" }}>
      <div>Type: {data.conditionType || "delivery"}</div>
      {data.conditionType === "template_replies" && data.selectedTemplate && (
        <div>Template: {data.selectedTemplate.template_name}</div>
      )}
      {data.conditionType === "user_response" && data.conditions && (
        <div>Conditions: {data.conditions.length}</div>
      )}
      {data.conditionType === "delivery" && (
        <div>Timeout: {data.deliveryTimeout || 30}s</div>
      )}
    </div>
    <Handle type="source" position={Position.Bottom} id="default" />
  </div>
);

const HttpRequestNode = ({ data = {}, selected, id }) => (
  <div
    style={{
      padding: 12,
      background: "white",
      border: selected ? "2px solid #1890ff" : "1px solid #ccc",
      borderRadius: 8,
      minWidth: 180,
      maxWidth: 250,
      position: "relative",
      boxShadow: selected
        ? "0 4px 12px rgba(24, 144, 255, 0.15)"
        : "0 2px 8px rgba(0,0,0,0.1)",
    }}
  >
    <Handle type="target" position={Position.Top} id="default" />

    {/* Delete Button */}
    <div
      style={{
        position: "absolute",
        top: "-8px",
        right: "-8px",
        background: "#ff4d4f",
        color: "white",
        border: "none",
        borderRadius: "50%",
        width: "20px",
        height: "20px",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        cursor: "pointer",
        fontSize: "12px",
        zIndex: 1000,
        opacity: selected ? 1 : 0.7,
        transition: "all 0.2s ease",
      }}
      onClick={(e) => {
        e.stopPropagation();
            requestNodeDelete(id);
      }}
      onMouseEnter={(e) => {
        e.currentTarget.style.transform = "scale(1.1)";
        e.currentTarget.style.background = "#ff7875";
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.transform = "scale(1)";
        e.currentTarget.style.background = "#ff4d4f";
      }}
      title="Delete Node"
    >
      <DeleteOutlined style={{ fontSize: "10px" }} />
    </div>

    <div style={{ display: "flex", alignItems: "center", marginBottom: 8 }}>
      <ApiOutlined style={{ color: "#1890ff", marginRight: 6 }} />
      <div style={{ fontWeight: "bold", fontSize: "14px" }}>Webhook</div>
    </div>

    <div style={{ fontSize: "11px", color: "#666", lineHeight: "1.4" }}>
      {data.enabled !== false ? (
        <>
          <div style={{ marginBottom: 4 }}>
            <span style={{ fontWeight: "500" }}>Status:</span>
            <span style={{ color: "#52c41a", marginLeft: 4 }}>● Active</span>
          </div>
          {data.url && (
            <div
              style={{
                wordBreak: "break-all",
                fontSize: "10px",
                color: "#888",
                marginTop: 4,
              }}
            >
              {data.url.length > 35
                ? data.url.substring(0, 35) + "..."
                : data.url}
            </div>
          )}
        </>
      ) : (
        <div style={{ color: "#ff4d4f" }}>
          <span style={{ fontWeight: "500" }}>Status:</span>
          <span style={{ marginLeft: 4 }}>● Disabled</span>
        </div>
      )}
    </div>

    <Handle type="source" position={Position.Bottom} id="default" />
  </div>
);

const JumpToStepNode = ({ data = {}, selected, id }) => (
  <div
    style={{
      padding: 12,
      background: "white",
      border: selected ? "2px solid #722ed1" : "1px solid #d3adf7",
      borderRadius: 10,
      minWidth: 170,
      maxWidth: 220,
      position: "relative",
      boxShadow: "0 2px 8px rgba(114, 46, 209, 0.08)",
    }}
  >
    <Handle
      type="target"
      position={Position.Top}
      id="default"
      style={{ background: "#722ed1", width: 10, height: 10 }}
    />

    {/* Delete Button */}
    <div
      style={{
        position: "absolute",
        top: "-8px",
        right: "-8px",
        background: "#ff4d4f",
        color: "white",
        border: "none",
        borderRadius: "50%",
        width: "20px",
        height: "20px",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        cursor: "pointer",
        fontSize: "12px",
        zIndex: 1000,
        opacity: selected ? 1 : 0.7,
        transition: "all 0.2s ease",
      }}
      onClick={(e) => {
        e.stopPropagation();
        requestNodeDelete(id);
      }}
      title="Delete Node"
    >
      <DeleteOutlined style={{ fontSize: "10px" }} />
    </div>

    <div style={{ display: "flex", alignItems: "center", gap: 6, fontWeight: "bold", marginBottom: 6, color: "#531dab" }}>
      <ThunderboltOutlined style={{ color: "#722ed1" }} />
      <span>{data.label || "Jump to Step"}</span>
    </div>
    <div style={{ fontSize: "12px", color: "#666" }}>
      <div>Target: <Tag color="purple" style={{ margin: 0, fontSize: 11 }}>{data.targetStep || data.targetNodeId || data.target_node || "Select Step"}</Tag></div>
    </div>
    <Handle
      type="source"
      position={Position.Bottom}
      id="default"
      style={{ background: "#722ed1", width: 10, height: 10 }}
    />
  </div>
);

const FunctionCallNode = ({ data = {}, selected, id }) => (
  <div
    style={{
      padding: 10,
      background: "white",
      border: selected ? "2px solid #1890ff" : "1px solid #ccc",
      borderRadius: 8,
      minWidth: 150,
      maxWidth: 200,
      position: "relative",
    }}
  >
    <Handle type="target" position={Position.Top} id="default" />

    {/* Delete Button */}
    <div
      style={{
        position: "absolute",
        top: "-8px",
        right: "-8px",
        background: "#ff4d4f",
        color: "white",
        border: "none",
        borderRadius: "50%",
        width: "20px",
        height: "20px",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        cursor: "pointer",
        fontSize: "12px",
        zIndex: 1000,
        opacity: selected ? 1 : 0.7,
        transition: "all 0.2s ease",
      }}
      onClick={(e) => {
        e.stopPropagation();
        requestNodeDelete(id);
      }}
      onMouseEnter={(e) => {
        e.currentTarget.style.transform = "scale(1.1)";
        e.currentTarget.style.background = "#ff7875";
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.transform = "scale(1)";
        e.currentTarget.style.background = "#ff4d4f";
      }}
      title="Delete Node"
    >
      <DeleteOutlined style={{ fontSize: "10px" }} />
    </div>

    <div style={{ fontWeight: "bold", marginBottom: 5 }}>Function Call</div>
    <div style={{ fontSize: "12px", color: "#666" }}>
      <div>Type: {data.functionType || "predefined"}</div>
    </div>
    <Handle type="source" position={Position.Bottom} id="default" />
  </div>
);

const DateTimeConditionNode = ({ data = {}, selected, id }) => {
  const mode = data.mode || data.conditionType || "business_hours";
  const startTime = data.start_time || "09:00";
  const endTime = data.end_time || "18:00";

  return (
    <div
      style={{
        padding: 12,
        background: "white",
        border: selected ? "2px solid #22c55e" : "1px solid #86efac",
        borderRadius: 10,
        minWidth: 190,
        maxWidth: 240,
        position: "relative",
        boxShadow: "0 2px 8px rgba(34, 197, 94, 0.08)",
      }}
    >
      <Handle
        type="target"
        position={Position.Top}
        id="default"
        style={{ background: "#22c55e", width: 10, height: 10 }}
      />

      {/* Delete Button */}
      <div
        style={{
          position: "absolute",
          top: "-8px",
          right: "-8px",
          background: "#ff4d4f",
          color: "white",
          border: "none",
          borderRadius: "50%",
          width: "20px",
          height: "20px",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          cursor: "pointer",
          fontSize: "12px",
          zIndex: 1000,
          opacity: selected ? 1 : 0.7,
          transition: "all 0.2s ease",
        }}
        onClick={(e) => {
          e.stopPropagation();
          requestNodeDelete(id);
        }}
        title="Delete Node"
      >
        <DeleteOutlined style={{ fontSize: "10px" }} />
      </div>

      <div style={{ display: "flex", alignItems: "center", gap: 6, fontWeight: "bold", marginBottom: 6, color: "#166534" }}>
        <CalendarOutlined style={{ color: "#22c55e" }} />
        <span>{data.label || "Business Hours"}</span>
      </div>
      <div style={{ fontSize: "12px", color: "#555", marginBottom: 8 }}>
        <div>Mode: <Tag color="green" style={{ margin: 0, fontSize: 10 }}>{mode}</Tag></div>
        {mode === "business_hours" && (
          <div style={{ marginTop: 3, color: "#666" }}>Hours: {startTime} - {endTime}</div>
        )}
      </div>

      {/* Two Branch Handles: Left = Open (Green), Right = Closed (Red) */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginTop: 8, paddingTop: 6, borderTop: "1px dashed #e2e8f0", fontSize: 11, fontWeight: 600 }}>
        <div style={{ color: "#16a34a", position: "relative" }}>
          <span>Open (YES)</span>
          <Handle
            type="source"
            position={Position.Bottom}
            id="open"
            style={{
              left: "25%",
              bottom: "-18px",
              background: "#22c55e",
              width: 12,
              height: 12,
              border: "2px solid white",
              cursor: "crosshair",
            }}
          />
        </div>
        <div style={{ color: "#dc2626", position: "relative" }}>
          <span>Closed (NO)</span>
          <Handle
            type="source"
            position={Position.Bottom}
            id="closed"
            style={{
              left: "75%",
              bottom: "-18px",
              background: "#ef4444",
              width: 12,
              height: 12,
              border: "2px solid white",
              cursor: "crosshair",
            }}
          />
        </div>
      </div>
    </div>
  );
};

const WaitForResponseNode = ({ data = {}, selected, id }) => {
  const timeout = data.timeout || 300;
  const enableTimeout = data.enableTimeout !== false;
  const enableResponseMatching = data.enableResponseMatching !== false;
  const enableQuickReplies = data.enableQuickReplies || false;
  const expectedResponses = data.expectedResponses || [];
  const quickReplies = data.quickReplies || [];

  return (
    <div
      style={{
        padding: 10,
        background: "white",
        border: selected ? "2px solid #1890ff" : "1px solid #ccc",
        borderRadius: 8,
        minWidth: 150,
        maxWidth: 200,
        position: "relative",
      }}
    >
      <Handle type="target" position={Position.Top} id="default" />

      {/* Delete Button */}
      <div
        style={{
          position: "absolute",
          top: "-8px",
          right: "-8px",
          background: "#ff4d4f",
          color: "white",
          border: "none",
          borderRadius: "50%",
          width: "20px",
          height: "20px",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          cursor: "pointer",
          fontSize: "12px",
          zIndex: 1000,
          opacity: selected ? 1 : 0.7,
          transition: "all 0.2s ease",
        }}
        onClick={(e) => {
          e.stopPropagation();
            requestNodeDelete(id);
        }}
        onMouseEnter={(e) => {
          e.currentTarget.style.transform = "scale(1.1)";
          e.currentTarget.style.background = "#ff7875";
        }}
        onMouseLeave={(e) => {
          e.currentTarget.style.transform = "scale(1)";
          e.currentTarget.style.background = "#ff4d4f";
        }}
        title="Delete Node"
      >
        <DeleteOutlined style={{ fontSize: "10px" }} />
      </div>

      <div style={{ fontWeight: "bold", marginBottom: 5 }}>
        <ClockCircleOutlined style={{ marginRight: 8 }} />
        Wait for Response
      </div>

      <div style={{ fontSize: "12px", color: "#666" }}>
        <div>Pause conversation and wait for user input</div>

        {enableTimeout && (
          <div style={{ marginTop: 5, color: "#fa8c16" }}>
            ⏱️ Timeout: {timeout}s
          </div>
        )}

        <div style={{ marginTop: 5 }}>
          {enableResponseMatching && (
            <span
              style={{
                background: "#e6f7ff",
                color: "#1890ff",
                padding: "2px 6px",
                borderRadius: "4px",
                fontSize: "10px",
                marginRight: "4px",
              }}
            >
              Response Matching ({expectedResponses.length})
            </span>
          )}
          {enableQuickReplies && (
            <span
              style={{
                background: "#f6ffed",
                color: "#52c41a",
                padding: "2px 6px",
                borderRadius: "4px",
                fontSize: "10px",
                marginRight: "4px",
              }}
            >
              Quick Replies ({quickReplies.length})
            </span>
          )}
        </div>

        {data.fallbackAction && (
          <div style={{ marginTop: 5, fontSize: "10px", color: "#999" }}>
            Fallback: {data.fallbackAction}
          </div>
        )}
      </div>

      {/* Quick Reply Output Handles */}
      {enableQuickReplies && quickReplies.length > 0 && (
        <div
          style={{
            marginTop: 8,
            position: "relative",
            borderTop: "1px solid #f0f0f0",
            paddingTop: 8,
          }}
        >
          <div
            style={{
              fontSize: "10px",
              color: "#666",
              marginBottom: 6,
              fontWeight: "500",
            }}
          >
            Quick Reply Connections:
          </div>
          <div
            style={{
              display: "flex",
              flexDirection: "column",
              gap: 4,
            }}
          >
            {quickReplies.map((reply, index) => (
              <div
                key={index}
                style={{
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  padding: "4px 8px",
                  background: "#f6ffed",
                  border: "1px solid #b7eb8f",
                  borderRadius: "4px",
                  fontSize: "11px",
                  position: "relative",
                }}
              >
                <div
                  style={{
                    display: "flex",
                    alignItems: "center",
                    flex: 1,
                  }}
                >
                  <div
                    style={{
                      width: "6px",
                      height: "6px",
                      borderRadius: "50%",
                      background: "#52c41a",
                      marginRight: "8px",
                      flexShrink: 0,
                    }}
                  />
                  <span
                    style={{
                      fontSize: "10px",
                      color: "#237804",
                      fontWeight: "500",
                      lineHeight: "1.2",
                    }}
                  >
                    {reply.text}
                  </span>
                </div>
                <Handle
                  type="source"
                  position={Position.Right}
                  id={`reply-${index}`}
                  className="quick-reply-handle"
                  style={{
                    width: 12,
                    height: 12,
                    marginLeft: 8,
                    border: "2px solid white",
                    boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
                  }}
                />
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Delivery Status Handles (hidden for Template Message node) */}
      {SHOW_LEGACY_DELIVERY_HANDLES && (
      <div
        style={{
          marginTop: 8,
          position: "relative",
          borderTop: "1px solid #f0f0f0",
          paddingTop: 8,
        }}
      >
        {/* Delivery Status Section Title */}
        <div
          style={{
            fontSize: "10px",
            color: "#666",
            fontWeight: "500",
            marginBottom: 6,
            textAlign: "center",
          }}
        >
          Delivery Status
        </div>

        <div
          style={{
            display: "flex",
            flexDirection: "column",
            gap: "4px",
          }}
        >
          {/* Unread Branch */}
          <div
            style={{
              display: "flex",
              alignItems: "center",
              background: "linear-gradient(135deg, #fff2e8 0%, #ffd8bf 100%)",
              border: "1px solid #ffbb96",
              borderRadius: "6px",
              padding: "6px 8px 6px 8px",
              paddingRight: "20px",
              margin: "0",
              position: "relative",
              transition: "all 0.2s ease",
              cursor: "pointer",
              boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.transform = "translateX(2px)";
              e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.transform = "translateX(0)";
              e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                flex: 1,
              }}
            >
              <div
                style={{
                  width: "6px",
                  height: "6px",
                  borderRadius: "50%",
                  background: "#fa8c16",
                  marginRight: "8px",
                  flexShrink: 0,
                }}
              />
              <span
                style={{
                  fontSize: "10px",
                  color: "#d46b08",
                  fontWeight: "600",
                  lineHeight: "1.2",
                }}
              >
                Unread
              </span>
            </div>
            <Handle
              type="source"
              position={Position.Right}
              id="unread"
              className="default-handle"
              style={{
                width: 12,
                height: 12,
                marginLeft: 8,
                border: "2px solid white",
                boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
              }}
            />
          </div>

          {/* Undelivered Branch */}
          <div
            style={{
              display: "flex",
              alignItems: "center",
              background: "linear-gradient(135deg, #fff1f0 0%, #ffccc7 100%)",
              border: "1px solid #ffa39e",
              borderRadius: "6px",
              padding: "6px 8px 6px 8px",
              paddingRight: "20px",
              margin: "0",
              position: "relative",
              transition: "all 0.2s ease",
              cursor: "pointer",
              boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.transform = "translateX(2px)";
              e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.transform = "translateX(0)";
              e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                flex: 1,
              }}
            >
              <div
                style={{
                  width: "6px",
                  height: "6px",
                  borderRadius: "50%",
                  background: "#f5222d",
                  marginRight: "8px",
                  flexShrink: 0,
                }}
              />
              <span
                style={{
                  fontSize: "10px",
                  color: "#cf1322",
                  fontWeight: "600",
                  lineHeight: "1.2",
                }}
              >
                Undelivered
              </span>
            </div>
            <Handle
              type="source"
              position={Position.Right}
              id="undelivered"
              className="default-handle"
              style={{
                width: 12,
                height: 12,
                marginLeft: 8,
                border: "2px solid white",
                boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
              }}
            />
          </div>
        </div>
      </div>
      )}

      {/* Default Output Handle */}
      <Handle type="source" position={Position.Bottom} id="default" />
    </div>
  );
};

const NaturalLanguageNode = ({ data = {}, selected, id }) => {
  const mode = data.mode || "knowledge_base";
  const botName = data.botName || "Default AI Bot";
  const intents = data.intents || ["sales", "support", "pricing", "general"];

  return (
    <div
      style={{
        padding: 12,
        background: "white",
        border: selected ? "2px solid #6366f1" : "1px solid #c7d2fe",
        borderRadius: 10,
        minWidth: 180,
        maxWidth: 240,
        position: "relative",
        boxShadow: "0 2px 8px rgba(99, 102, 241, 0.08)",
      }}
    >
      <Handle
        type="target"
        position={Position.Top}
        id="default"
        style={{ background: "#6366f1", width: 10, height: 10 }}
      />

      {/* Delete Button */}
      <div
        style={{
          position: "absolute",
          top: "-8px",
          right: "-8px",
          background: "#ff4d4f",
          color: "white",
          border: "none",
          borderRadius: "50%",
          width: "20px",
          height: "20px",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          cursor: "pointer",
          fontSize: "12px",
          zIndex: 1000,
          opacity: selected ? 1 : 0.7,
          transition: "all 0.2s ease",
        }}
        onClick={(e) => {
          e.stopPropagation();
          requestNodeDelete(id);
        }}
        title="Delete Node"
      >
        <DeleteOutlined style={{ fontSize: "10px" }} />
      </div>

      <div style={{ display: "flex", alignItems: "center", gap: 6, fontWeight: "bold", marginBottom: 6, color: "#4338ca" }}>
        <RobotOutlined style={{ color: "#6366f1" }} />
        <span>{data.label || "AI Natural Language"}</span>
      </div>
      <div style={{ fontSize: "12px", color: "#666", marginBottom: 6 }}>
        <div>Mode: <Tag color="blue" style={{ margin: 0, fontSize: 10 }}>{mode === "knowledge_base" ? "Knowledge Base" : mode === "custom_prompt" ? "Custom Prompt" : "Intent Classifier"}</Tag></div>
        <div style={{ marginTop: 3 }}>Bot: <span style={{ fontWeight: 500, color: "#334155" }}>{botName}</span></div>
      </div>

      {mode === "intent_classification" ? (
        <div style={{ marginTop: 8, paddingTop: 6, borderTop: "1px dashed #e2e8f0" }}>
          <div style={{ fontSize: 11, fontWeight: 600, color: "#6366f1", marginBottom: 4 }}>Intent Branches:</div>
          <div style={{ display: "flex", flexWrap: "wrap", gap: 4 }}>
            {intents.map((intent) => (
              <div key={intent} style={{ position: "relative", marginBottom: 12 }}>
                <Tag color="purple" style={{ fontSize: 10, margin: 0 }}>{intent}</Tag>
                <Handle
                  type="source"
                  position={Position.Bottom}
                  id={`output_intent_${intent}`}
                  style={{
                    bottom: -16,
                    background: "#8b5cf6",
                    width: 10,
                    height: 10,
                    border: "2px solid white",
                    cursor: "crosshair",
                  }}
                />
              </div>
            ))}
          </div>
          <Handle
            type="source"
            position={Position.Bottom}
            id="default"
            style={{ background: "#6366f1", width: 10, height: 10 }}
          />
        </div>
      ) : (
        <Handle
          type="source"
          position={Position.Bottom}
          id="default"
          style={{ background: "#6366f1", width: 10, height: 10 }}
        />
      )}
    </div>
  );
};

// Enhanced Template Message Node with Multiple Output Handles
const TemplateMessageNode = ({ data = {}, selected, id }) => {
  const quickReplies = data.quickReplies || [];

  // Debug logging
  console.log("TemplateMessageNode data:", data);
  console.log("Quick replies:", quickReplies);
  console.log("Selected template:", data.selectedTemplate);

  return (
    <div
      style={{
        padding: 10,
        background: "white",
        border: selected ? "2px solid #1890ff" : "1px solid #ccc",
        borderRadius: 8,
        minWidth: 150,
        maxWidth: 200,
        position: "relative",
      }}
    >
      <Handle type="target" position={Position.Top} id="default" />

      {/* Delete Button */}
      <div
        style={{
          position: "absolute",
          top: "-8px",
          right: "-8px",
          background: "#ff4d4f",
          color: "white",
          border: "none",
          borderRadius: "50%",
          width: "20px",
          height: "20px",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          cursor: "pointer",
          fontSize: "12px",
          zIndex: 1000,
          opacity: selected ? 1 : 0.7,
          transition: "all 0.2s ease",
        }}
        onClick={(e) => {
          e.stopPropagation();
            requestNodeDelete(id);
        }}
        onMouseEnter={(e) => {
          e.currentTarget.style.transform = "scale(1.1)";
          e.currentTarget.style.background = "#ff7875";
        }}
        onMouseLeave={(e) => {
          e.currentTarget.style.transform = "scale(1)";
          e.currentTarget.style.background = "#ff4d4f";
        }}
        title="Delete Node"
      >
        <DeleteOutlined style={{ fontSize: "10px" }} />
      </div>

      <div style={{ fontWeight: "bold", marginBottom: 5 }}>
        {data.label || "Template Message"}
      </div>
      <div style={{ fontSize: "12px", color: "#666" }}>
        {data.messageType === "template" && data.selectedTemplate && (
          <div>Template: {data.selectedTemplate.template_name}</div>
        )}
        {data.messageType === "text" && data.customText && (
          <div>Message: {data.customText.substring(0, 20)}...</div>
        )}
        {data.selectedTemplate &&
          (data.selectedTemplate.button_type == 2 ||
            data.selectedTemplate.button_type == 4) && (
          <div>Quick Reply Template</div>
        )}
        <div>Send: {data.sendImmediately ? "Immediate" : "On Interaction"}</div>
        {/* Debug info */}
        <div style={{ fontSize: "10px", color: "#999" }}>
          Quick Replies: {quickReplies.length}
        </div>
      </div>

      {/* Multiple output handles for quick replies */}
      {(quickReplies.length > 0 ||
        (data.selectedTemplate &&
          (data.selectedTemplate.button_type == 2 ||
            data.selectedTemplate.button_type == 4))) && (
          <div
            style={{
              marginTop: 8,
              position: "relative",
              borderTop: "1px solid #f0f0f0",
              paddingTop: 8,
            }}
          >
            {/* Quick Reply Section Title */}
            <div
              style={{
                fontSize: "10px",
                color: "#666",
                fontWeight: "500",
                marginBottom: 6,
                textAlign: "center",
              }}
            >
              Quick Reply Branches
            </div>

            {/* Quick Reply Handles - Enhanced Layout */}
            <div
              style={{
                display: "flex",
                flexDirection: "column",
                gap: "6px",
                marginBottom: 8,
              }}
            >
              {quickReplies.length > 0
                ? quickReplies.map((reply, index) => (
                  <div
                    key={`reply-${index}`}
                    style={{
                      display: "flex",
                      alignItems: "center",
                      background:
                        "linear-gradient(135deg, #f6ffed 0%, #d9f7be 100%)",
                      border: "1px solid #b7eb8f",
                      borderRadius: "6px",
                      padding: "6px 8px 6px 8px",
                      paddingRight: "20px",
                      margin: "0",
                      position: "relative",
                      transition: "all 0.2s ease",
                      cursor: "pointer",
                      boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
                    }}
                    title={reply.text} // Tooltip for full text
                    onMouseEnter={(e) => {
                      e.currentTarget.style.transform = "translateX(2px)";
                      e.currentTarget.style.boxShadow =
                        "0 2px 6px rgba(0,0,0,0.15)";
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.transform = "translateX(0)";
                      e.currentTarget.style.boxShadow =
                        "0 1px 3px rgba(0,0,0,0.1)";
                    }}
                  >
                    <div
                      style={{
                        display: "flex",
                        alignItems: "center",
                        flex: 1,
                      }}
                    >
                      <div
                        style={{
                          width: "6px",
                          height: "6px",
                          borderRadius: "50%",
                          background: "#52c41a",
                          marginRight: "8px",
                          flexShrink: 0,
                        }}
                      />
                      <span
                        style={{
                          fontSize: "10px",
                          color: "#237804",
                          fontWeight: "500",
                          lineHeight: "1.2",
                        }}
                      >
                        {reply.text}
                      </span>
                    </div>
                    <Handle
                      type="source"
                      position={Position.Right}
                      id={`reply-${index}`}
                      className="quick-reply-handle"
                      style={{
                        width: 12,
                        height: 12,
                        border: "2px solid white",
                        boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
                      }}
                    />
                  </div>
                ))
                : // Fallback: show default handles if template is quick reply but no quick replies extracted
                [1, 2, 3].map((index) => (
                  <div
                    key={`fallback-${index}`}
                    style={{
                      display: "flex",
                      alignItems: "center",
                      background:
                        "linear-gradient(135deg, #f6ffed 0%, #d9f7be 100%)",
                      border: "1px solid #b7eb8f",
                      borderRadius: "6px",
                      padding: "6px 8px 6px 8px",
                      paddingRight: "20px",
                      margin: "0",
                      position: "relative",
                      transition: "all 0.2s ease",
                      cursor: "pointer",
                      boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
                    }}
                    onMouseEnter={(e) => {
                      e.currentTarget.style.transform = "translateX(2px)";
                      e.currentTarget.style.boxShadow =
                        "0 2px 6px rgba(0,0,0,0.15)";
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.transform = "translateX(0)";
                      e.currentTarget.style.boxShadow =
                        "0 1px 3px rgba(0,0,0,0.1)";
                    }}
                  >
                    <div
                      style={{
                        display: "flex",
                        alignItems: "center",
                        flex: 1,
                      }}
                    >
                      <div
                        style={{
                          width: "6px",
                          height: "6px",
                          borderRadius: "50%",
                          background: "#52c41a",
                          marginRight: "8px",
                          flexShrink: 0,
                        }}
                      />
                      <span
                        style={{
                          fontSize: "10px",
                          color: "#237804",
                          fontWeight: "500",
                          lineHeight: "1.2",
                        }}
                      >
                        Reply {index}
                      </span>
                    </div>
                    <Handle
                      type="source"
                      position={Position.Right}
                      id={`reply-${index}`}
                      className="quick-reply-handle"
                      style={{
                        width: 12,
                        height: 12,
                        border: "2px solid white",
                        boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
                      }}
                    />
                  </div>
                ))}
            </div>

            {/* Default branch handle */}
            <div
              style={{
                display: "flex",
                alignItems: "center",
                background: "linear-gradient(135deg, #fff7e6 0%, #ffeaa7 100%)",
                border: "1px solid #ffd591",
                borderRadius: "6px",
                padding: "6px 8px 6px 8px",
                paddingRight: "20px",
                margin: "4px 0",
                position: "relative",
                transition: "all 0.2s ease",
                cursor: "pointer",
                boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
                display: "none",
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.transform = "translateX(2px)";
                e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.transform = "translateX(0)";
                e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
              }}
            >
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  flex: 1,
                }}
              >
                <div
                  style={{
                    width: "6px",
                    height: "6px",
                    borderRadius: "50%",
                    background: "#faad14",
                    marginRight: "8px",
                    flexShrink: 0,
                  }}
                />
                <span
                  style={{
                    fontSize: "10px",
                    color: "#d48806",
                    fontWeight: "600",
                    lineHeight: "1.2",
                  }}
                >
                  Default
                </span>
              </div>
              <Handle
                type="source"
                position={Position.Right}
                id="default"
                className="default-handle"
                style={{
                  width: 12,
                  height: 12,
                  marginLeft: 8,
                  border: "2px solid white",
                  boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
                }}
              />
            </div>
          </div>
        )}

      {/* Delivery Status Handles */}
      {SHOW_LEGACY_DELIVERY_HANDLES && (
      <div
        style={{
          marginTop: 8,
          position: "relative",
          borderTop: "1px solid #f0f0f0",
          paddingTop: 8,
        }}
      >
        {/* Delivery Status Section Title */}
        <div
          style={{
            fontSize: "10px",
            color: "#666",
            fontWeight: "500",
            marginBottom: 6,
            textAlign: "center",
          }}
        >
          Delivery Status
        </div>

        <div
          style={{
            display: "flex",
            flexDirection: "column",
            gap: "4px",
          }}
        >
          {/* Delivered Branch */}
          <div
            style={{
              display: "flex",
              alignItems: "center",
              background: "linear-gradient(135deg, #f6ffed 0%, #d9f7be 100%)",
              border: "1px solid #b7eb8f",
              borderRadius: "6px",
              padding: "6px 8px 6px 8px",
              paddingRight: "20px",
              margin: "0",
              position: "relative",
              transition: "all 0.2s ease",
              cursor: "pointer",
              boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.transform = "translateX(2px)";
              e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.transform = "translateX(0)";
              e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                flex: 1,
              }}
            >
              <div
                style={{
                  width: "6px",
                  height: "6px",
                  borderRadius: "50%",
                  background: "#52c41a",
                  marginRight: "8px",
                  flexShrink: 0,
                }}
              />
              <span
                style={{
                  fontSize: "10px",
                  color: "#237804",
                  fontWeight: "600",
                  lineHeight: "1.2",
                }}
              >
                Delivered
              </span>
            </div>
            <Handle
              type="source"
              position={Position.Right}
              id="delivered"
              className="quick-reply-handle"
              style={{
                width: 12,
                height: 12,
                marginLeft: 8,
                border: "2px solid white",
                boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
              }}
            />
          </div>

          {/* Unread Branch */}
          <div
            style={{
              display: "flex",
              alignItems: "center",
              background: "linear-gradient(135deg, #fff2e8 0%, #ffd8bf 100%)",
              border: "1px solid #ffbb96",
              borderRadius: "6px",
              padding: "6px 8px 6px 8px",
              paddingRight: "20px",
              margin: "0",
              position: "relative",
              transition: "all 0.2s ease",
              cursor: "pointer",
              boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.transform = "translateX(2px)";
              e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.transform = "translateX(0)";
              e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                flex: 1,
              }}
            >
              <div
                style={{
                  width: "6px",
                  height: "6px",
                  borderRadius: "50%",
                  background: "#fa8c16",
                  marginRight: "8px",
                  flexShrink: 0,
                }}
              />
              <span
                style={{
                  fontSize: "10px",
                  color: "#d46b08",
                  fontWeight: "600",
                  lineHeight: "1.2",
                }}
              >
                Unread
              </span>
            </div>
            <Handle
              type="source"
              position={Position.Right}
              id="unread"
              className="default-handle"
              style={{
                width: 12,
                height: 12,
                marginLeft: 8,
                border: "2px solid white",
                boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
              }}
            />
          </div>

          {/* Undelivered Branch */}
          <div
            style={{
              display: "flex",
              alignItems: "center",
              background: "linear-gradient(135deg, #fff1f0 0%, #ffccc7 100%)",
              border: "1px solid #ffa39e",
              borderRadius: "6px",
              padding: "6px 8px 6px 8px",
              paddingRight: "20px",
              margin: "0",
              position: "relative",
              transition: "all 0.2s ease",
              cursor: "pointer",
              boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.transform = "translateX(2px)";
              e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.transform = "translateX(0)";
              e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                flex: 1,
              }}
            >
              <div
                style={{
                  width: "6px",
                  height: "6px",
                  borderRadius: "50%",
                  background: "#f5222d",
                  marginRight: "8px",
                  flexShrink: 0,
                }}
              />
              <span
                style={{
                  fontSize: "10px",
                  color: "#cf1322",
                  fontWeight: "600",
                  lineHeight: "1.2",
                }}
              >
                Undelivered
              </span>
            </div>
            <Handle
              type="source"
              position={Position.Right}
              id="undelivered"
              className="default-handle"
              style={{
                width: 12,
                height: 12,
                marginLeft: 8,
                border: "2px solid white",
                boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
              }}
            />
          </div>
        </div>
      </div>
      )}

      {/* Default output handle when no quick replies exist */}
      {!quickReplies.length &&
        (!data.selectedTemplate ||
          (data.selectedTemplate.button_type != 2 &&
            data.selectedTemplate.button_type != 4)) && (
          <div
            style={{
              marginTop: 8,
              position: "relative",
              borderTop: "1px solid #f0f0f0",
              paddingTop: 8,
            }}
          >
            <div
              style={{
                fontSize: "10px",
                color: "#666",
                fontWeight: "500",
                marginBottom: 6,
                textAlign: "center",
              }}
            >
              Continue Flow
            </div>
            <div
              style={{
                display: "flex",
                alignItems: "center",
                background: "linear-gradient(135deg, #e6f7ff 0%, #bae7ff 100%)",
                border: "1px solid #91d5ff",
                borderRadius: "6px",
                padding: "6px 8px 6px 8px",
                paddingRight: "20px",
                margin: "0",
                position: "relative",
                transition: "all 0.2s ease",
                cursor: "pointer",
                boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.transform = "translateX(2px)";
                e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.transform = "translateX(0)";
                e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
              }}
            >
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  flex: 1,
                }}
              >
                <div
                  style={{
                    width: "6px",
                    height: "6px",
                    borderRadius: "50%",
                    background: "#1890ff",
                    marginRight: "8px",
                    flexShrink: 0,
                  }}
                />
                <span
                  style={{
                    fontSize: "10px",
                    color: "#096dd9",
                    fontWeight: "600",
                    lineHeight: "1.2",
                  }}
                >
                  Next Node
                </span>
              </div>
              <Handle
                type="source"
                position={Position.Right}
                id="default"
                className="default-handle"
                style={{
                  width: 12,
                  height: 12,
                  marginLeft: 8,
                  border: "2px solid white",
                  boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
                }}
              />
            </div>
          </div>
        )}
    </div>
  );
};

const DelayNode = ({ data = {}, selected, id }) => (
  <div
    style={{
      padding: 10,
      background: "white",
      border: selected ? "2px solid #1890ff" : "1px solid #ccc",
      borderRadius: 8,
      minWidth: 150,
      maxWidth: 200,
      position: "relative",
    }}
  >
    <Handle type="target" position={Position.Top} id="default" />

    {/* Delete Button */}
    <div
      style={{
        position: "absolute",
        top: "-8px",
        right: "-8px",
        background: "#ff4d4f",
        color: "white",
        border: "none",
        borderRadius: "50%",
        width: "20px",
        height: "20px",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        cursor: "pointer",
        fontSize: "12px",
        zIndex: 1000,
        opacity: selected ? 1 : 0.7,
        transition: "all 0.2s ease",
      }}
      onClick={(e) => {
        e.stopPropagation();
            requestNodeDelete(id);
      }}
      onMouseEnter={(e) => {
        e.currentTarget.style.transform = "scale(1.1)";
        e.currentTarget.style.background = "#ff7875";
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.transform = "scale(1)";
        e.currentTarget.style.background = "#ff4d4f";
      }}
      title="Delete Node"
    >
      <DeleteOutlined style={{ fontSize: "10px" }} />
    </div>

    <div style={{ fontWeight: "bold", marginBottom: 5 }}>Delay</div>
    <div style={{ fontSize: "12px", color: "#666" }}>
      <div>Type: {data.delayType || "fixed"}</div>
      {data.delayType === "fixed" && (
        <div>Duration: {data.delayInSeconds || 5}s</div>
      )}
      {data.delayType === "until_time" && data.delayUntilTime && (
        <div>Until: {data.delayUntilTime}</div>
      )}
      {data.delayType === "until_date" && data.delayUntilDate && (
        <div>Until: {data.delayUntilDate}</div>
      )}
      {data.showProgress && (
        <div>Progress: {data.progressMessage || "Please wait..."}</div>
      )}
      {data.showProgress && data.completionMessage && (
        <div>Completion: {data.completionMessage}</div>
      )}
    </div>
    <Handle type="source" position={Position.Bottom} id="default" />
  </div>
);

const InteractiveMessageNode = ({ data = {}, selected, id }) => {
  const sections = data.sections || [];
  const buttons = data.buttons || [];
  const totalRows = sections.reduce(
    (total, section) => total + (section.rows || []).length,
    0
  );

  // Debug logging
  console.log("InteractiveMessageNode data:", data);
  console.log("Sections:", sections);
  console.log("Buttons:", buttons);
  console.log("Interactive type:", data.interactiveType);

  return (
    <div
      style={{
        padding: 10,
        background: "white",
        border: selected ? "2px solid #1890ff" : "1px solid #ccc",
        borderRadius: 8,
        minWidth: 150,
        maxWidth: 200,
        position: "relative",
      }}
    >
      <Handle type="target" position={Position.Top} id="default" />

      {/* Delete Button */}
      <div
        style={{
          position: "absolute",
          top: "-8px",
          right: "-8px",
          background: "#ff4d4f",
          color: "white",
          border: "none",
          borderRadius: "50%",
          width: "20px",
          height: "20px",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          cursor: "pointer",
          fontSize: "12px",
          zIndex: 1000,
          opacity: selected ? 1 : 0.7,
          transition: "all 0.2s ease",
        }}
        onClick={(e) => {
          e.stopPropagation();
          requestNodeDelete(id);
        }}
        onMouseEnter={(e) => {
          e.currentTarget.style.transform = "scale(1.1)";
          e.currentTarget.style.background = "#ff7875";
        }}
        onMouseLeave={(e) => {
          e.currentTarget.style.transform = "scale(1)";
          e.currentTarget.style.background = "#ff4d4f";
        }}
        title="Delete Node"
      >
        <DeleteOutlined style={{ fontSize: "10px" }} />
      </div>
      <div style={{ fontWeight: "bold", marginBottom: 5 }}>
        Interactive Message
      </div>
      <div style={{ fontSize: "12px", color: "#666" }}>
        <div>Type: {data.interactiveType || "list"}</div>
        {data.headerText && (
          <div>Header: {data.headerText.substring(0, 20)}...</div>
        )}
        {data.bodyText && <div>Body: {data.bodyText.substring(0, 20)}...</div>}
        {data.interactiveType === "list" && (
          <div>
            Sections: {sections.length} • Rows: {totalRows}
          </div>
        )}
        {data.interactiveType === "button" && (
          <div>Buttons: {buttons.length}</div>
        )}
        {data.interactiveType === "product" && <div>Single Product</div>}
        {data.interactiveType === "product_list" && (
          <div>Multi Product ({sections.length} sections)</div>
        )}
        {data.interactiveType === "catalog_message" && (
          <div>Product Catalog</div>
        )}
        {data.interactiveType === "cta_url" && <div>CTA Button</div>}
        {data.interactiveType === "flow" && <div>WhatsApp Flow</div>}
        {data.interactiveType === "location_request" && (
          <div>Location Request</div>
        )}
        {data.interactiveType === "address" && <div>Address Request</div>}
        {/* Debug info */}
        <div style={{ fontSize: "10px", color: "#999" }}>
          Interactive Options:{" "}
          {data.interactiveType === "list"
            ? totalRows
            : data.interactiveType === "button"
              ? buttons.length
              : "Continue Flow"}
        </div>
      </div>

      {/* Multiple output handles for interactive options */}
      {data.interactiveType === "list" && sections.length > 0 && (
        <div
          style={{
            marginTop: 8,
            position: "relative",
            borderTop: "1px solid #f0f0f0",
            paddingTop: 8,
          }}
        >
          {/* Interactive Options Section Title */}
          <div
            style={{
              fontSize: "10px",
              color: "#666",
              fontWeight: "500",
              marginBottom: 6,
              textAlign: "center",
            }}
          >
            List Options
          </div>

          {/* Interactive Option Handles - Enhanced Layout */}
          <div
            style={{
              display: "flex",
              flexDirection: "column",
              gap: "6px",
              marginBottom: 8,
            }}
          >
            {sections.map((section, sectionIndex) =>
              (section.rows || []).map((row, rowIndex) => (
                <div
                  key={`${sectionIndex}-${rowIndex}`}
                  style={{
                    display: "flex",
                    alignItems: "center",
                    background:
                      "linear-gradient(135deg, #e6f7ff 0%, #bae7ff 100%)",
                    border: "1px solid #91d5ff",
                    borderRadius: "6px",
                    padding: "6px 8px 6px 8px",
                    paddingRight: "20px",
                    margin: "0",
                    position: "relative",
                    transition: "all 0.2s ease",
                    cursor: "pointer",
                    boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
                  }}
                  title={`${section.title || `Section ${sectionIndex + 1}`}: ${row.title
                    }`}
                  onMouseEnter={(e) => {
                    e.currentTarget.style.transform = "translateX(2px)";
                    e.currentTarget.style.boxShadow =
                      "0 2px 6px rgba(0,0,0,0.15)";
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.transform = "translateX(0)";
                    e.currentTarget.style.boxShadow =
                      "0 1px 3px rgba(0,0,0,0.1)";
                  }}
                >
                  <div
                    style={{
                      display: "flex",
                      alignItems: "center",
                      flex: 1,
                    }}
                  >
                    <div
                      style={{
                        width: "6px",
                        height: "6px",
                        borderRadius: "50%",
                        background: "#1890ff",
                        marginRight: "8px",
                        flexShrink: 0,
                      }}
                    />
                    <span
                      style={{
                        fontSize: "10px",
                        color: "#0050b3",
                        fontWeight: "500",
                        lineHeight: "1.2",
                      }}
                    >
                      {row.title || row.id}
                    </span>
                  </div>
                  <Handle
                    type="source"
                    position={Position.Right}
                    id={`interactive-${sectionIndex}-${rowIndex}`}
                    className="quick-reply-handle"
                    style={{
                      width: 12,
                      height: 12,
                      marginLeft: 8,
                      border: "2px solid white",
                      boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
                    }}
                  />
                </div>
              ))
            )}
          </div>
        </div>
      )}

      {/* Button handles for button messages */}
      {data.interactiveType === "button" && buttons.length > 0 && (
        <div
          style={{
            marginTop: 8,
            position: "relative",
            borderTop: "1px solid #f0f0f0",
            paddingTop: 8,
          }}
        >
          {/* Button Options Section Title */}
          <div
            style={{
              fontSize: "10px",
              color: "#666",
              fontWeight: "500",
              marginBottom: 6,
              textAlign: "center",
            }}
          >
            Button Options
          </div>

          {/* Button Handles */}
          <div
            style={{
              display: "flex",
              flexDirection: "column",
              gap: "6px",
              marginBottom: 8,
            }}
          >
            {buttons.map((button, buttonIndex) => (
              <div
                key={`button-${buttonIndex}`}
                style={{
                  display: "flex",
                  alignItems: "center",
                  background:
                    "linear-gradient(135deg, #f6ffed 0%, #d9f7be 100%)",
                  border: "1px solid #b7eb8f",
                  borderRadius: "6px",
                  padding: "6px 8px 6px 8px",
                  paddingRight: "20px",
                  margin: "0",
                  position: "relative",
                  transition: "all 0.2s ease",
                  cursor: "pointer",
                  boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
                }}
                title={`${button.title} (Button)`}
                onMouseEnter={(e) => {
                  e.currentTarget.style.transform = "translateX(2px)";
                  e.currentTarget.style.boxShadow =
                    "0 2px 6px rgba(0,0,0,0.15)";
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.transform = "translateX(0)";
                  e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
                }}
              >
                <div
                  style={{
                    display: "flex",
                    alignItems: "center",
                    flex: 1,
                  }}
                >
                  <div
                    style={{
                      width: "6px",
                      height: "6px",
                      borderRadius: "50%",
                      background: "#52c41a",
                      marginRight: "8px",
                      flexShrink: 0,
                    }}
                  />
                  <span
                    style={{
                      fontSize: "10px",
                      color: "#237804",
                      fontWeight: "500",
                      lineHeight: "1.2",
                    }}
                  >
                    {button.title}
                  </span>
                </div>
                <Handle
                  type="source"
                  position={Position.Right}
                  id={`button-${buttonIndex}`}
                  className="quick-reply-handle"
                  style={{
                    width: 12,
                    height: 12,
                    marginLeft: 8,
                    border: "2px solid white",
                    boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
                  }}
                />
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Delivery Status Handles */}
      {SHOW_LEGACY_DELIVERY_HANDLES &&
        ((data.interactiveType === "list" && sections.length > 0) ||
        (data.interactiveType === "button" && buttons.length > 0) ||
        data.interactiveType === "product" ||
        data.interactiveType === "product_list" ||
        data.interactiveType === "catalog_message" ||
        data.interactiveType === "cta_url" ||
        data.interactiveType === "flow" ||
        data.interactiveType === "location_request" ||
        data.interactiveType === "address") ? (
        <div
          style={{
            marginTop: 8,
            position: "relative",
            borderTop: "1px solid #f0f0f0",
            paddingTop: 8,
          }}
        >
          {/* Delivery Status Section Title */}
          <div
            style={{
              fontSize: "10px",
              color: "#666",
              fontWeight: "500",
              marginBottom: 6,
              textAlign: "center",
            }}
          >
            Delivery Status
          </div>

          <div
            style={{
              display: "flex",
              flexDirection: "column",
              gap: "4px",
            }}
          >
            {/* Delivered Branch */}
            <div
              style={{
                display: "flex",
                alignItems: "center",
                background: "linear-gradient(135deg, #f6ffed 0%, #d9f7be 100%)",
                border: "1px solid #b7eb8f",
                borderRadius: "6px",
                padding: "6px 8px 6px 8px",
                paddingRight: "20px",
                margin: "0",
                position: "relative",
                transition: "all 0.2s ease",
                cursor: "pointer",
                boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.transform = "translateX(2px)";
                e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.transform = "translateX(0)";
                e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
              }}
            >
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  flex: 1,
                }}
              >
                <div
                  style={{
                    width: "6px",
                    height: "6px",
                    borderRadius: "50%",
                    background: "#52c41a",
                    marginRight: "8px",
                    flexShrink: 0,
                  }}
                />
                <span
                  style={{
                    fontSize: "10px",
                    color: "#237804",
                    fontWeight: "600",
                    lineHeight: "1.2",
                  }}
                >
                  Delivered
                </span>
              </div>
              <Handle
                type="source"
                position={Position.Right}
                id="delivered"
                className="quick-reply-handle"
                style={{
                  width: 12,
                  height: 12,
                  marginLeft: 8,
                  border: "2px solid white",
                  boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
                }}
              />
            </div>

            {/* Unread Branch */}
            <div
              style={{
                display: "flex",
                alignItems: "center",
                background: "linear-gradient(135deg, #fff2e8 0%, #ffd8bf 100%)",
                border: "1px solid #ffbb96",
                borderRadius: "6px",
                padding: "6px 8px 6px 8px",
                paddingRight: "20px",
                margin: "0",
                position: "relative",
                transition: "all 0.2s ease",
                cursor: "pointer",
                boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.transform = "translateX(2px)";
                e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.transform = "translateX(0)";
                e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
              }}
            >
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  flex: 1,
                }}
              >
                <div
                  style={{
                    width: "6px",
                    height: "6px",
                    borderRadius: "50%",
                    background: "#fa8c16",
                    marginRight: "8px",
                    flexShrink: 0,
                  }}
                />
                <span
                  style={{
                    fontSize: "10px",
                    color: "#d46b08",
                    fontWeight: "600",
                    lineHeight: "1.2",
                  }}
                >
                  Unread
                </span>
              </div>
              <Handle
                type="source"
                position={Position.Right}
                id="unread"
                className="default-handle"
                style={{
                  width: 12,
                  height: 12,
                  marginLeft: 8,
                  border: "2px solid white",
                  boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
                }}
              />
            </div>

            {/* Undelivered Branch */}
            <div
              style={{
                display: "flex",
                alignItems: "center",
                background: "linear-gradient(135deg, #fff1f0 0%, #ffccc7 100%)",
                border: "1px solid #ffa39e",
                borderRadius: "6px",
                padding: "6px 8px 6px 8px",
                paddingRight: "20px",
                margin: "0",
                position: "relative",
                transition: "all 0.2s ease",
                cursor: "pointer",
                boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.transform = "translateX(2px)";
                e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.transform = "translateX(0)";
                e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
              }}
            >
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  flex: 1,
                }}
              >
                <div
                  style={{
                    width: "6px",
                    height: "6px",
                    borderRadius: "50%",
                    background: "#f5222d",
                    marginRight: "8px",
                    flexShrink: 0,
                  }}
                />
                <span
                  style={{
                    fontSize: "10px",
                    color: "#cf1322",
                    fontWeight: "600",
                    lineHeight: "1.2",
                  }}
                >
                  Undelivered
                </span>
              </div>
              <Handle
                type="source"
                position={Position.Right}
                id="undelivered"
                className="default-handle"
                style={{
                  width: 12,
                  height: 12,
                  marginLeft: 8,
                  border: "2px solid white",
                  boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
                }}
              />
            </div>
          </div>
        </div>
      ) : null}

      {/* Default output handle when not using multi-branch list/button handles */}
      {!(data.interactiveType === "list" && sections.length > 0) &&
        !(data.interactiveType === "button" && buttons.length > 0) && (
          <div
            style={{
              marginTop: 8,
              position: "relative",
              borderTop: "1px solid #f0f0f0",
              paddingTop: 8,
            }}
          >
            <div
              style={{
                fontSize: "10px",
                color: "#666",
                fontWeight: "500",
                marginBottom: 6,
                textAlign: "center",
              }}
            >
              Continue Flow
            </div>
            <div
              style={{
                display: "flex",
                alignItems: "center",
                background: "linear-gradient(135deg, #e6f7ff 0%, #bae7ff 100%)",
                border: "1px solid #91d5ff",
                borderRadius: "6px",
                padding: "6px 8px 6px 8px",
                paddingRight: "20px",
                margin: "0",
                position: "relative",
                transition: "all 0.2s ease",
                cursor: "pointer",
                boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.transform = "translateX(2px)";
                e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.transform = "translateX(0)";
                e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
              }}
            >
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  flex: 1,
                }}
              >
                <div
                  style={{
                    width: "6px",
                    height: "6px",
                    borderRadius: "50%",
                    background: "#1890ff",
                    marginRight: "8px",
                    flexShrink: 0,
                  }}
                />
                <span
                  style={{
                    fontSize: "10px",
                    color: "#096dd9",
                    fontWeight: "600",
                    lineHeight: "1.2",
                  }}
                >
                  Next Node
                </span>
              </div>
              <Handle
                type="source"
                position={Position.Right}
                id="default"
                className="default-handle"
                style={{
                  width: 12,
                  height: 12,
                  marginLeft: 8,
                  border: "2px solid white",
                  boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
                }}
              />
            </div>
          </div>
        )}
    </div>
  );
};

// Carousel Template Node
const CarouselTemplateNode = ({ data, selected, id }) => {
  const templateCards = data.templateCards || [];
  const totalButtons = templateCards.reduce(
    (total, card) => total + (card.buttons || []).length,
    0
  );
  const quickReplyButtons = templateCards.reduce(
    (total, card) =>
      total +
      (card.buttons || []).filter((btn) => btn.type === "QUICK_REPLY").length,
    0
  );

  // Debug logging
  console.log("CarouselTemplateNode data:", data);
  console.log("Template cards:", templateCards);
  console.log("Selected template:", data.selectedTemplate);

  return (
    <div
      style={{
        padding: 10,
        background: "white",
        border: selected ? "2px solid #1890ff" : "1px solid #ccc",
        borderRadius: 8,
        minWidth: 150,
        maxWidth: 200,
        position: "relative",
      }}
    >
      <Handle type="target" position={Position.Top} id="default" />

      {/* Delete Button */}
      <div
        style={{
          position: "absolute",
          top: "-8px",
          right: "-8px",
          background: "#ff4d4f",
          color: "white",
          border: "none",
          borderRadius: "50%",
          width: "20px",
          height: "20px",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          cursor: "pointer",
          fontSize: "12px",
          zIndex: 1000,
          opacity: selected ? 1 : 0.7,
          transition: "all 0.2s ease",
        }}
        onClick={(e) => {
          e.stopPropagation();
          requestNodeDelete(id);
        }}
        onMouseEnter={(e) => {
          e.currentTarget.style.transform = "scale(1.1)";
          e.currentTarget.style.background = "#ff7875";
        }}
        onMouseLeave={(e) => {
          e.currentTarget.style.transform = "scale(1)";
          e.currentTarget.style.background = "#ff4d4f";
        }}
        title="Delete Node"
      >
        <DeleteOutlined style={{ fontSize: "10px" }} />
      </div>
      <div style={{ fontWeight: "bold", marginBottom: 5 }}>
        Carousel Template
      </div>
      <div style={{ fontSize: "12px", color: "#666" }}>
        {data.selectedTemplate && (
          <div>Template: {data.selectedTemplate.template_name}</div>
        )}
        <div>Cards: {templateCards.length}</div>
        <div>Total Buttons: {totalButtons}</div>
        <div>Quick Reply Buttons: {quickReplyButtons}</div>
        {/* Debug info */}
        <div style={{ fontSize: "10px", color: "#999" }}>
          Carousel Options: {quickReplyButtons}
        </div>
      </div>

      {/* Multiple output handles for carousel card buttons */}
      {templateCards.length > 0 && (
        <div
          style={{
            marginTop: 8,
            position: "relative",
            borderTop: "1px solid #f0f0f0",
            paddingTop: 8,
          }}
        >
          {/* Carousel Options Section Title */}
          <div
            style={{
              fontSize: "10px",
              color: "#666",
              fontWeight: "500",
              marginBottom: 6,
              textAlign: "center",
            }}
          >
            Carousel Options
          </div>

          {/* Carousel Card Button Handles - Only Quick Reply Buttons */}
          <div
            style={{
              display: "flex",
              flexDirection: "column",
              gap: "6px",
              marginBottom: 8,
            }}
          >
            {templateCards.map((card, cardIndex) =>
              (card.buttons || [])
                .filter((button) => button.type === "QUICK_REPLY") // Only show Quick Reply buttons
                .map((button, buttonIndex) => {
                  // Find the actual index of this Quick Reply button in the original buttons array
                  const originalButtonIndex = card.buttons.findIndex(
                    (btn, idx) =>
                      btn.type === "QUICK_REPLY" &&
                      card.buttons
                        .filter((b) => b.type === "QUICK_REPLY")
                        .indexOf(btn) === buttonIndex
                  );

                  return (
                    <div
                      key={`card-${cardIndex}-button-${originalButtonIndex}`}
                      style={{
                        display: "flex",
                        alignItems: "center",
                        background:
                          "linear-gradient(135deg, #e6f7ff 0%, #bae7ff 100%)",
                        border: "1px solid #91d5ff",
                        borderRadius: "6px",
                        padding: "6px 8px 6px 8px",
                        paddingRight: "20px",
                        margin: "0",
                        position: "relative",
                        transition: "all 0.2s ease",
                        cursor: "pointer",
                        boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
                      }}
                      title={`Card ${cardIndex + 1}: ${button.text
                        } (Quick Reply)`}
                      onMouseEnter={(e) => {
                        e.currentTarget.style.transform = "translateX(2px)";
                        e.currentTarget.style.boxShadow =
                          "0 2px 6px rgba(0,0,0,0.15)";
                      }}
                      onMouseLeave={(e) => {
                        e.currentTarget.style.transform = "translateX(0)";
                        e.currentTarget.style.boxShadow =
                          "0 1px 3px rgba(0,0,0,0.1)";
                      }}
                    >
                      <div
                        style={{
                          display: "flex",
                          alignItems: "center",
                          flex: 1,
                        }}
                      >
                        <div
                          style={{
                            width: "6px",
                            height: "6px",
                            borderRadius: "50%",
                            background: "#1890ff",
                            marginRight: "8px",
                            flexShrink: 0,
                          }}
                        />
                        <span
                          style={{
                            fontSize: "10px",
                            color: "#0050b3",
                            fontWeight: "500",
                            lineHeight: "1.2",
                          }}
                        >
                          {button.text}
                        </span>
                      </div>
                      <Handle
                        type="source"
                        position={Position.Right}
                        id={`carousel-${cardIndex}-${originalButtonIndex}`}
                        className="quick-reply-handle"
                        style={{
                          width: 12,
                          height: 12,
                          marginLeft: 8,
                          border: "2px solid white",
                          boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
                        }}
                      />
                    </div>
                  );
                })
            )}
          </div>
        </div>
      )}

      {/* Delivery Status Handles */}
      {SHOW_LEGACY_DELIVERY_HANDLES && (
      <div
        style={{
          marginTop: 8,
          position: "relative",
          borderTop: "1px solid #f0f0f0",
          paddingTop: 8,
        }}
      >
        {/* Delivery Status Section Title */}
        <div
          style={{
            fontSize: "10px",
            color: "#666",
            fontWeight: "500",
            marginBottom: 6,
            textAlign: "center",
          }}
        >
          Delivery Status
        </div>

        <div
          style={{
            display: "flex",
            flexDirection: "column",
            gap: "4px",
          }}
        >
          {/* Delivered Branch */}
          <div
            style={{
              display: "flex",
              alignItems: "center",
              background: "linear-gradient(135deg, #f6ffed 0%, #d9f7be 100%)",
              border: "1px solid #b7eb8f",
              borderRadius: "6px",
              padding: "6px 8px 6px 8px",
              paddingRight: "20px",
              margin: "0",
              position: "relative",
              transition: "all 0.2s ease",
              cursor: "pointer",
              boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.transform = "translateX(2px)";
              e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.transform = "translateX(0)";
              e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                flex: 1,
              }}
            >
              <div
                style={{
                  width: "6px",
                  height: "6px",
                  borderRadius: "50%",
                  background: "#52c41a",
                  marginRight: "8px",
                  flexShrink: 0,
                }}
              />
              <span
                style={{
                  fontSize: "10px",
                  color: "#237804",
                  fontWeight: "600",
                  lineHeight: "1.2",
                }}
              >
                Delivered
              </span>
            </div>
            <Handle
              type="source"
              position={Position.Right}
              id="delivered"
              className="quick-reply-handle"
              style={{
                width: 12,
                height: 12,
                marginLeft: 8,
                border: "2px solid white",
                boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
              }}
            />
          </div>

          {/* Unread Branch */}
          <div
            style={{
              display: "flex",
              alignItems: "center",
              background: "linear-gradient(135deg, #fff2e8 0%, #ffd8bf 100%)",
              border: "1px solid #ffbb96",
              borderRadius: "6px",
              padding: "6px 8px 6px 8px",
              paddingRight: "20px",
              margin: "0",
              position: "relative",
              transition: "all 0.2s ease",
              cursor: "pointer",
              boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.transform = "translateX(2px)";
              e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.transform = "translateX(0)";
              e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                flex: 1,
              }}
            >
              <div
                style={{
                  width: "6px",
                  height: "6px",
                  borderRadius: "50%",
                  background: "#fa8c16",
                  marginRight: "8px",
                  flexShrink: 0,
                }}
              />
              <span
                style={{
                  fontSize: "10px",
                  color: "#d46b08",
                  fontWeight: "600",
                  lineHeight: "1.2",
                }}
              >
                Unread
              </span>
            </div>
            <Handle
              type="source"
              position={Position.Right}
              id="unread"
              className="default-handle"
              style={{
                width: 12,
                height: 12,
                marginLeft: 8,
                border: "2px solid white",
                boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
              }}
            />
          </div>

          {/* Undelivered Branch */}
          <div
            style={{
              display: "flex",
              alignItems: "center",
              background: "linear-gradient(135deg, #fff1f0 0%, #ffccc7 100%)",
              border: "1px solid #ffa39e",
              borderRadius: "6px",
              padding: "6px 8px 6px 8px",
              paddingRight: "20px",
              margin: "0",
              position: "relative",
              transition: "all 0.2s ease",
              cursor: "pointer",
              boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.transform = "translateX(2px)";
              e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.transform = "translateX(0)";
              e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                flex: 1,
              }}
            >
              <div
                style={{
                  width: "6px",
                  height: "6px",
                  borderRadius: "50%",
                  background: "#f5222d",
                  marginRight: "8px",
                  flexShrink: 0,
                }}
              />
              <span
                style={{
                  fontSize: "10px",
                  color: "#cf1322",
                  fontWeight: "600",
                  lineHeight: "1.2",
                }}
              >
                Undelivered
              </span>
            </div>
            <Handle
              type="source"
              position={Position.Right}
              id="undelivered"
              className="default-handle"
              style={{
                width: 12,
                height: 12,
                marginLeft: 8,
                border: "2px solid white",
                boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
              }}
            />
          </div>
        </div>
      </div>
      )}

      {/* Default output handle when no carousel cards configured yet */}
      {templateCards.length === 0 && (
        <div
          style={{
            marginTop: 8,
            position: "relative",
            borderTop: "1px solid #f0f0f0",
            paddingTop: 8,
          }}
        >
          <div
            style={{
              fontSize: "10px",
              color: "#666",
              fontWeight: "500",
              marginBottom: 6,
              textAlign: "center",
            }}
          >
            Continue Flow
          </div>
          <div
            style={{
              display: "flex",
              alignItems: "center",
              background: "linear-gradient(135deg, #e6f7ff 0%, #bae7ff 100%)",
              border: "1px solid #91d5ff",
              borderRadius: "6px",
              padding: "6px 8px 6px 8px",
              paddingRight: "20px",
              margin: "0",
              position: "relative",
              transition: "all 0.2s ease",
              cursor: "pointer",
              boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.transform = "translateX(2px)";
              e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.transform = "translateX(0)";
              e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                flex: 1,
              }}
            >
              <div
                style={{
                  width: "6px",
                  height: "6px",
                  borderRadius: "50%",
                  background: "#1890ff",
                  marginRight: "8px",
                  flexShrink: 0,
                }}
              />
              <span
                style={{
                  fontSize: "10px",
                  color: "#096dd9",
                  fontWeight: "600",
                  lineHeight: "1.2",
                }}
              >
                Next Node
              </span>
            </div>
            <Handle
              type="source"
              position={Position.Right}
              id="default"
              className="default-handle"
              style={{
                width: 12,
                height: 12,
                marginLeft: 8,
                border: "2px solid white",
                boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
              }}
            />
          </div>
        </div>
      )}
    </div>
  );
};

// WhatsApp Flow Template Node
const WhatsAppFlowTemplateNode = ({ data, selected, id }) => {
  // Debug logging
  console.log("WhatsAppFlowTemplateNode data:", data);
  console.log("Selected template:", data.selectedTemplate);

  return (
    <div
      style={{
        padding: 10,
        background: "white",
        border: selected ? "2px solid #1890ff" : "1px solid #ccc",
        borderRadius: 8,
        minWidth: 150,
        maxWidth: 200,
        position: "relative",
      }}
    >
      <Handle type="target" position={Position.Top} id="default" />

      {/* Delete Button */}
      <div
        style={{
          position: "absolute",
          top: "-8px",
          right: "-8px",
          background: "#ff4d4f",
          color: "white",
          border: "none",
          borderRadius: "50%",
          width: "20px",
          height: "20px",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          cursor: "pointer",
          fontSize: "12px",
          zIndex: 1000,
          opacity: selected ? 1 : 0.7,
          transition: "all 0.2s ease",
        }}
        onClick={(e) => {
          e.stopPropagation();
            requestNodeDelete(id);
        }}
        onMouseEnter={(e) => {
          e.currentTarget.style.transform = "scale(1.1)";
          e.currentTarget.style.background = "#ff7875";
        }}
        onMouseLeave={(e) => {
          e.currentTarget.style.transform = "scale(1)";
          e.currentTarget.style.background = "#ff4d4f";
        }}
        title="Delete Node"
      >
        <DeleteOutlined style={{ fontSize: "10px" }} />
      </div>
      <div style={{ fontWeight: "bold", marginBottom: 5 }}>
        {data.label || "WhatsApp Flow Template"}
      </div>
      <div style={{ fontSize: "12px", color: "#666" }}>
        {data.selectedTemplate && (
          <div>Template: {data.selectedTemplate.template_name}</div>
        )}
        {data.flowToken && (
          <div>Flow Token: {data.flowToken.substring(0, 15)}...</div>
        )}
        <div>Flow Action: {data.flowAction || "Continue"}</div>
        <div>Send: {data.sendImmediately ? "Immediate" : "On Interaction"}</div>
        {/* Debug info */}
        <div style={{ fontSize: "10px", color: "#999" }}>
          WhatsApp Flow Template
        </div>
      </div>

      {/* Delivery Status Handles */}
      {SHOW_LEGACY_DELIVERY_HANDLES && (
      <div
        style={{
          marginTop: 8,
          position: "relative",
          borderTop: "1px solid #f0f0f0",
          paddingTop: 8,
        }}
      >
        {/* Delivery Status Section Title */}
        <div
          style={{
            fontSize: "10px",
            color: "#666",
            fontWeight: "500",
            marginBottom: 6,
            textAlign: "center",
          }}
        >
          Delivery Status
        </div>

        <div
          style={{
            display: "flex",
            flexDirection: "column",
            gap: "4px",
          }}
        >
          {/* Unread Branch */}
          <div
            style={{
              display: "flex",
              alignItems: "center",
              background: "linear-gradient(135deg, #fff2e8 0%, #ffd8bf 100%)",
              border: "1px solid #ffbb96",
              borderRadius: "6px",
              padding: "6px 8px 6px 8px",
              paddingRight: "20px",
              margin: "0",
              position: "relative",
              transition: "all 0.2s ease",
              cursor: "pointer",
              boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.transform = "translateX(2px)";
              e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.transform = "translateX(0)";
              e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                flex: 1,
              }}
            >
              <div
                style={{
                  width: "6px",
                  height: "6px",
                  borderRadius: "50%",
                  background: "#fa8c16",
                  marginRight: "8px",
                  flexShrink: 0,
                }}
              />
              <span
                style={{
                  fontSize: "10px",
                  color: "#d46b08",
                  fontWeight: "600",
                  lineHeight: "1.2",
                }}
              >
                Unread
              </span>
            </div>
            <Handle
              type="source"
              position={Position.Right}
              id="unread"
              className="default-handle"
              style={{
                width: 12,
                height: 12,
                marginLeft: 8,
                border: "2px solid white",
                boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
              }}
            />
          </div>

          {/* Undelivered Branch */}
          <div
            style={{
              display: "flex",
              alignItems: "center",
              background: "linear-gradient(135deg, #fff1f0 0%, #ffccc7 100%)",
              border: "1px solid #ffa39e",
              borderRadius: "6px",
              padding: "6px 8px 6px 8px",
              paddingRight: "20px",
              margin: "0",
              position: "relative",
              transition: "all 0.2s ease",
              cursor: "pointer",
              boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.transform = "translateX(2px)";
              e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.transform = "translateX(0)";
              e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                flex: 1,
              }}
            >
              <div
                style={{
                  width: "6px",
                  height: "6px",
                  borderRadius: "50%",
                  background: "#f5222d",
                  marginRight: "8px",
                  flexShrink: 0,
                }}
              />
              <span
                style={{
                  fontSize: "10px",
                  color: "#cf1322",
                  fontWeight: "600",
                  lineHeight: "1.2",
                }}
              >
                Undelivered
              </span>
            </div>
            <Handle
              type="source"
              position={Position.Right}
              id="undelivered"
              className="default-handle"
              style={{
                width: 12,
                height: 12,
                marginLeft: 8,
                border: "2px solid white",
                boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
              }}
            />
          </div>
        </div>
      </div>
      )}

      {/* Response Endpoint Handles */}
      <div
        style={{
          marginTop: 8,
          position: "relative",
          borderTop: "1px solid #f0f0f0",
          paddingTop: 8,
        }}
      >
        {/* Response Endpoint Section Title */}
        <div
          style={{
            fontSize: "10px",
            color: "#666",
            fontWeight: "500",
            marginBottom: 6,
            textAlign: "center",
          }}
        >
          Response Endpoints
        </div>

        <div
          style={{
            display: "flex",
            flexDirection: "column",
            gap: "4px",
          }}
        >
          {/* Default Output (Fallback) */}
          <div
            style={{
              display: "flex",
              alignItems: "center",
              background: "linear-gradient(135deg, #f6ffed 0%, #d9f7be 100%)",
              border: "1px solid #b7eb8f",
              borderRadius: "6px",
              padding: "6px 8px 6px 8px",
              paddingRight: "20px",
              margin: "0",
              position: "relative",
              transition: "all 0.2s ease",
              cursor: "pointer",
              boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.transform = "translateX(2px)";
              e.currentTarget.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.transform = "translateX(0)";
              e.currentTarget.style.boxShadow = "0 1px 3px rgba(0,0,0,0.1)";
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                flex: 1,
              }}
            >
              <div
                style={{
                  width: "6px",
                  height: "6px",
                  borderRadius: "50%",
                  background: "#52c41a",
                  marginRight: "8px",
                  flexShrink: 0,
                }}
              />
              <span
                style={{
                  fontSize: "10px",
                  color: "#389e0d",
                  fontWeight: "600",
                  lineHeight: "1.2",
                }}
              >
                Default (Fallback)
              </span>
            </div>
            <Handle
              type="source"
              position={Position.Right}
              id="default"
              className="default-handle"
              style={{
                width: 12,
                height: 12,
                marginLeft: 8,
                border: "2px solid white",
                boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
              }}
            />
          </div>

          {/* Interactive Response Outputs */}
          {data.interactiveResponses &&
            data.interactiveResponses.length > 0 && (
              <>
                {data.interactiveResponses.map((response, index) => (
                  <div
                    key={index}
                    style={{
                      display: "flex",
                      alignItems: "center",
                      background:
                        "linear-gradient(135deg, #e6f7ff 0%, #bae7ff 100%)",
                      border: "1px solid #91d5ff",
                      borderRadius: "6px",
                      padding: "6px 8px 6px 8px",
                      paddingRight: "20px",
                      margin: "0",
                      position: "relative",
                      transition: "all 0.2s ease",
                      cursor: "pointer",
                      boxShadow: "0 1px 3px rgba(0,0,0,0.1)",
                    }}
                    onMouseEnter={(e) => {
                      e.currentTarget.style.transform = "translateX(2px)";
                      e.currentTarget.style.boxShadow =
                        "0 2px 6px rgba(0,0,0,0.15)";
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.transform = "translateX(0)";
                      e.currentTarget.style.boxShadow =
                        "0 1px 3px rgba(0,0,0,0.1)";
                    }}
                  >
                    <div
                      style={{
                        display: "flex",
                        alignItems: "center",
                        flex: 1,
                      }}
                    >
                      <div
                        style={{
                          width: "6px",
                          height: "6px",
                          borderRadius: "50%",
                          background: "#1890ff",
                          marginRight: "8px",
                          flexShrink: 0,
                        }}
                      />
                      <span
                        style={{
                          fontSize: "10px",
                          color: "#096dd9",
                          fontWeight: "600",
                          lineHeight: "1.2",
                        }}
                      >
                        {response.text ||
                          response.id ||
                          `Response ${index + 1}`}
                      </span>
                    </div>
                    <Handle
                      type="source"
                      position={Position.Right}
                      id={`reply-${index}`}
                      className="default-handle"
                      style={{
                        width: 12,
                        height: 12,
                        border: "2px solid white",
                        boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
                      }}
                    />
                  </div>
                ))}
              </>
            )}

          {/* Add Response Button (when response matching is enabled) */}
          {data.enableResponseMatching && (
            <div
              style={{
                display: "flex",
                alignItems: "center",
                background: "linear-gradient(135deg, #fff7e6 0%, #ffd591 100%)",
                border: "1px dashed #ffa940",
                borderRadius: "6px",
                padding: "6px 8px",
                margin: "4px 0 0 0",
                cursor: "pointer",
                transition: "all 0.2s ease",
                fontSize: "10px",
                color: "#d46b08",
                fontWeight: "500",
                textAlign: "center",
                justifyContent: "center",
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.background =
                  "linear-gradient(135deg, #fff2e8 0%, #ffbb96 100%)";
                e.currentTarget.style.borderColor = "#fa8c16";
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.background =
                  "linear-gradient(135deg, #fff7e6 0%, #ffd591 100%)";
                e.currentTarget.style.borderColor = "#ffa940";
              }}
              onClick={() => {
                // This will trigger the add response functionality in the module
                console.log("Add response clicked - configure in module");
              }}
            >
              <PlusOutlined style={{ marginRight: "4px", fontSize: "10px" }} />
              Add Response
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

// Media Node Component
const MediaNode = ({ data, selected, id }) => {
  const getMediaIcon = (mediaType) => {
    switch (mediaType) {
      case "image":
        return "🖼️";
      case "video":
        return "🎥";
      case "audio":
        return "🎵";
      case "document":
        return "📄";
      default:
        return "📎";
    }
  };

  const getMediaColor = (mediaType) => {
    switch (mediaType) {
      case "image":
        return "#52c41a";
      case "video":
        return "#1890ff";
      case "audio":
        return "#722ed1";
      case "document":
        return "#fa8c16";
      default:
        return "#666";
    }
  };

  return (
    <div
      style={{
        padding: 10,
        background: "white",
        border: selected ? "2px solid #1890ff" : "1px solid #ccc",
        borderRadius: 8,
        minWidth: 150,
        maxWidth: 200,
        position: "relative",
      }}
    >
      <Handle type="target" position={Position.Top} id="default" />

      {/* Delete Button */}
      <div
        style={{
          position: "absolute",
          top: "-8px",
          right: "-8px",
          background: "#ff4d4f",
          color: "white",
          border: "none",
          borderRadius: "50%",
          width: "20px",
          height: "20px",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          cursor: "pointer",
          fontSize: "12px",
          zIndex: 1000,
          opacity: selected ? 1 : 0.7,
          transition: "all 0.2s ease",
        }}
        onClick={(e) => {
          e.stopPropagation();
            requestNodeDelete(id);
        }}
        onMouseEnter={(e) => {
          e.currentTarget.style.transform = "scale(1.1)";
          e.currentTarget.style.background = "#ff7875";
        }}
        onMouseLeave={(e) => {
          e.currentTarget.style.transform = "scale(1)";
          e.currentTarget.style.background = "#ff4d4f";
        }}
        title="Delete Node"
      >
        <DeleteOutlined style={{ fontSize: "10px" }} />
      </div>

      <div
        style={{
          fontWeight: "bold",
          marginBottom: 5,
          display: "flex",
          alignItems: "center",
          gap: 5,
        }}
      >
        {/* Show media preview if available, otherwise show icon */}
        {data.fileUrl && !data.isPlaceholder ? (
          <div
            style={{
              width: "24px",
              height: "24px",
              borderRadius: "4px",
              overflow: "hidden",
            }}
          >
            {data.mediaType === "image" ? (
              <Image
                src={
                  data.fileUrl.startsWith("http")
                    ? data.fileUrl
                    : `${window.location.origin}/${data.fileUrl}`
                }
                alt="Media preview"
                style={{ width: "100%", height: "100%", objectFit: "cover" }}
                preview={false}
                fallback="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAMIAAADDCAYAAADQvc6UAAABRWlDQ1BJQ0MgUHJvZmlsZQAAKJFjYGASSSwoyGFhYGDIzSspCnJ3UoiIjFJgf8LAwSDCIMogwMCcmFxc4BgQ4ANUwgCjUcG3awyMIPqyLsis7PPOq3QdDFcvjV3jOD1boQVTPQrgSkktTgbSf4A4LbmgqISBgTEFyFYuLykAsTuAbJEioKOA7DkgdjqEvQHEToKwj4DVhAQ5A9k3gGyB5IxEoBmML4BsnSQk8XQkNtReEOBxcfXxUQg1Mjc0dyHgXNJBSWpFCYh2zi+oLMpMzyhRcASGUqqCZ16yno6CkYGRAQMDKMwhqj/fAIcloxgHQqxAjIHBEugw5sUIsSQpBobtQPdLciLEVJYzMPBHMDBsayhILEqEO4DxG0txmrERhM29nYGBddr//5/DGRjYNRkY/l7////39v///y4Dmn+LgeHANwDrkl1AuO+pmgAAADhlWElmTU0AKgAAAAgAAYdpAAQAAAABAAAAGgAAAAAAAqACAAQAAAABAAAAwqADAAQAAAABAAAAwwAAAAD9b/HnAAAHlklEQVR4Ae3dP3Ik1RnG4W+FgYxN"
              />
            ) : data.mediaType === "video" ? (
              <video
                src={
                  data.fileUrl.startsWith("http")
                    ? data.fileUrl
                    : `${window.location.origin}/${data.fileUrl}`
                }
                style={{ width: "100%", height: "100%", objectFit: "cover" }}
                muted
              />
            ) : (
              <span style={{ fontSize: "16px" }}>
                {getMediaIcon(data.mediaType || "document")}
              </span>
            )}
          </div>
        ) : (
          <span style={{ fontSize: "16px" }}>
            {getMediaIcon(data.mediaType || "document")}
          </span>
        )}
        {data.label || "Media Message"}
      </div>
      <div style={{ fontSize: "12px", color: "#666" }}>
        {data.mediaType && (
          <div
            style={{ color: getMediaColor(data.mediaType), fontWeight: "bold" }}
          >
            Type: {data.mediaType.toUpperCase()}
          </div>
        )}
        {data.caption && data.caption.trim() && (
          <div>Caption: {data.caption.substring(0, 30)}...</div>
        )}
        {data.isPlaceholder ? (
          <div style={{ color: "#fa8c16", fontStyle: "italic" }}>
            📝 Placeholder - No file uploaded
          </div>
        ) : (
          <>
            {/* Show larger media preview if available */}
            {data.fileUrl && data.mediaType === "image" && (
              <div style={{ margin: "4px 0", textAlign: "center" }}>
                <div onClick={(e) => e.stopPropagation()}>
                  <Image
                    src={
                      data.fileUrl.startsWith("http")
                        ? data.fileUrl
                        : `${window.location.origin}/${data.fileUrl}`
                    }
                    alt="Media preview"
                    style={{
                      width: "100%",
                      maxWidth: "120px",
                      objectFit: "cover",
                      borderRadius: "4px",
                      border: "1px solid #d9d9d9",
                    }}
                    preview={{
                      mask: "Preview",
                      maskClassName: "media-preview-mask",
                    }}
                    fallback="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAMIAAADDCAYAAADQvc6UAAABRWlDQ1BJQ0MgUHJvZmlsZQAAKJFjYGASSSwoyGFhYGDIzSspCnJ3UoiIjFJgf8LAwSDCIMogwMCcmFxc4BgQ4ANUwgCjUcG3awyMIPqyLsis7PPOq3QdDFcvjV3jOD1boQVTPQrgSkktTgbSf4A4LbmgqISBgTEFyFYuLykAsTuAbJEioKOA7DkgdjqEvQHEToKwj4DVhAQ5A9k3gGyB5IxEoBmML4BsnSQk8XQkNtReEOBxcfXxUQg1Mjc0dyHgXNJBSWpFCYh2zi+oLMpMzyhRcASGUqqCZ16yno6CkYGRAQMDKMwhqj/fAIcloxgHQqxAjIHBEugw5sUIsSQpBobtQPdLciLEVJYzMPBHMDBsayhILEqEO4DxG0txmrERhM29nYGBddr//5/DGRjYNRkY/l7////39v///y4Dmn+LgeHANwDrkl1AuO+pmgAAADhlWElmTU0AKgAAAAgAAYdpAAQAAAABAAAAGgAAAAAAAqACAAQAAAABAAAAwqADAAQAAAABAAAAwwAAAAD9b/HnAAAHlklEQVR4Ae3dP3Ik1RnG4W+FgYxN"
                  />
                </div>
              </div>
            )}
            {data.fileName && <div>File: {data.fileName}</div>}
            {data.fileSize && (
              <div>Size: {(data.fileSize / 1024 / 1024).toFixed(2)} MB</div>
            )}
          </>
        )}
      </div>

      {/* Output handle */}
      <Handle
        type="source"
        position={Position.Bottom}
        id="default"
        style={{
          left: "50%",
          background: getMediaColor(data.mediaType || "document"),
          border: "2px solid white",
          boxShadow: "0 2px 4px rgba(0,0,0,0.3)",
          cursor: "crosshair",
          width: "12px",
          height: "12px",
        }}
        className="default-handle"
      />
    </div>
  );
};

// Custom Edge Component for Quick Reply Branching
const QuickReplyEdge = ({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
  style = {},
  markerEnd,
  data,
}) => {
  const [edgePath, labelX, labelY] = getBezierPath({
    sourceX,
    sourceY,
    sourcePosition,
    targetX,
    targetY,
    targetPosition,
  });

  return (
    <>
      <path
        id={id}
        style={style}
        className="react-flow__edge-path"
        d={edgePath}
        markerEnd={markerEnd}
      />
      {data && data.label && (
        <foreignObject
          width={120}
          height={40}
          x={labelX - 60}
          y={labelY - 20}
          className="edgebutton-foreignobject"
          requiredExtensions="http://www.w3.org/1999/xhtml"
        >
          <div
            style={{
              background: "#1890ff",
              color: "white",
              padding: "4px 8px",
              borderRadius: "4px",
              fontSize: "10px",
              textAlign: "center",
              border: "1px solid white",
              boxShadow: "0 2px 4px rgba(0,0,0,0.2)",
            }}
          >
            {data.label}
          </div>
        </foreignObject>
      )}
    </>
  );
};

// Node Types Configuration - Defined outside component to avoid recreation
const nodeTypes = {
  welcomeMessage: WelcomeMessageNode,
  typingIndicator: TypingIndicatorNode,
  enhancedCondition: EnhancedConditionNode,
  httpRequest: HttpRequestNode,
  jumpToStep: JumpToStepNode,
  functionCall: FunctionCallNode,
  dateTimeCondition: DateTimeConditionNode,
  waitForResponse: WaitForResponseNode,
  naturalLanguage: NaturalLanguageNode,
  templateMessage: TemplateMessageNode,
  delay: DelayNode,
  interactiveMessage: InteractiveMessageNode,
  carouselTemplate: CarouselTemplateNode,
  whatsappFlowTemplate: WhatsAppFlowTemplateNode,
  mediaMessage: MediaNode,
};

// Custom Default Edge Component
const DefaultEdge = (props) => {
  const isSelected = props.selected;
  console.log(
    "🔗 Rendering default edge:",
    props.id,
    props.source,
    props.target,
    props.sourceHandle,
    props.targetHandle,
    "Selected:",
    isSelected,
    "Coordinates:",
    {
      sourceX: props.sourceX,
      sourceY: props.sourceY,
      targetX: props.targetX,
      targetY: props.targetY,
    }
  );

  // Ensure we have valid coordinates
  if (!props.sourceX || !props.sourceY || !props.targetX || !props.targetY) {
    console.error("❌ Invalid edge coordinates:", {
      sourceX: props.sourceX,
      sourceY: props.sourceY,
      targetX: props.targetX,
      targetY: props.targetY,
    });
    return null;
  }

  return (
    <BaseEdge
      {...props}
      style={{
        ...props.style,
        stroke: isSelected ? "#1890ff" : "#faad14",
        strokeWidth: isSelected ? 4 : 2,
        strokeDasharray: isSelected ? "5,5" : "none",
        zIndex: 1,
        cursor: "pointer",
        opacity: isSelected ? 1 : 0.8,
      }}
    />
  );
};

// Edge Types Configuration
// const edgeTypes = {
//   quickReply: QuickReplyEdge,
//   // Temporarily disable custom edge to test default React Flow edges
//   // default: DefaultEdge,
//   // smoothstep: DefaultEdge,
// };

const edgeTypes = {
  quickReply: VisibleChatbotEdge,
  smoothstep: VisibleChatbotEdge,
  default: VisibleChatbotEdge,
  step: VisibleChatbotEdge,
  straight: VisibleChatbotEdge,
};

// Main Component
const ChatBotFlowReactFlow = () => {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [reactFlowInstance, setReactFlowInstance] = useState(null);
  const [selectedNode, setSelectedNode] = useState(null);
  const [selectedEdge, setSelectedEdge] = useState(null);
  const [currentConfiguringNodeId, setCurrentConfiguringNodeId] =
    useState(null);
  const [templates, setTemplates] = useState([]);
  const [interactiveMessages, setInteractiveMessages] = useState([]);
  const [aiBots, setAiBots] = useState([]);
  const [loading, setLoading] = useState(false);
  const [saveLoading, setSaveLoading] = useState(false);

  // Module visibility states
  const [welcomeMessageModule, setWelcomeMessageModule] = useState(false);
  const [typingIndicatorModule, setTypingIndicatorModule] = useState(false);
  const [enhancedConditionModule, setEnhancedConditionModule] = useState(false);
  const [httpRequestModule, setHttpRequestModule] = useState(false);
  const [jumpToStepModule, setJumpToStepModule] = useState(false);
  const [functionCallModule, setFunctionCallModule] = useState(false);
  const [dateTimeConditionModule, setDateTimeConditionModule] = useState(false);
  const [waitForResponseModule, setWaitForResponseModule] = useState(false);
  const [naturalLanguageModule, setNaturalLanguageModule] = useState(false);
  const [templateMessageModule, setTemplateMessageModule] = useState(false);
  const [delayModule, setDelayModule] = useState(false);
  const [interactiveMessageModule, setInteractiveMessageModule] =
    useState(false);
  const [carouselTemplateModule, setCarouselTemplateModule] = useState(false);
  const [whatsappFlowTemplateModule, setWhatsappFlowTemplateModule] =
    useState(false);
  const [mediaModule, setMediaModule] = useState(false);
  const [aiGeneratorVisible, setAiGeneratorVisible] = useState(false);

  // Global variables state
  const [globalVariables, setGlobalVariables] = useState([]);

  const nodesInitialized = useNodesInitialized();
  const updateNodeInternals = useUpdateNodeInternals();

  useEffect(() => {
    if (!nodesInitialized || nodes.length === 0) {
      return;
    }

    updateNodeInternals(nodes.map((node) => node.id));
  }, [nodesInitialized, nodes, updateNodeInternals]);

  // Flow identity from Blade mount config
  const builderConfig = getBuilderConfig();
  const uid = builderConfig.flowUuid || window.location.pathname.split("/").pop();

  // Inject custom CSS
  useEffect(() => {
    const styleElement = document.createElement("style");
    styleElement.textContent = customStyles;
    document.head.appendChild(styleElement);

    return () => {
      document.head.removeChild(styleElement);
    };
  }, []);

  // Fetch templates and automation bot data on component mount
  useEffect(() => {
    fetchTemplates();
    fetchAutomationBotData();
  }, []);

  const handleNodeDelete = useCallback(
    (nodeId) => {
      setNodes((nds) => nds.filter((node) => node.id !== nodeId));
      setEdges((eds) =>
        eds.filter((edge) => edge.source !== nodeId && edge.target !== nodeId)
      );

      if (selectedNode?.id === nodeId) {
        setSelectedNode(null);
        setCurrentConfiguringNodeId(null);
      }

      message.success("Node deleted successfully");
    },
    [selectedNode, setNodes, setEdges]
  );

  const performEdgeDelete = useCallback((edgeId) => {
    setEdges((eds) => eds.filter((edge) => edge.id !== edgeId));
    setSelectedEdge(null);
    message.success("Connection deleted successfully");
  }, [setEdges]);

  const handleEdgeDelete = useCallback((edgeId) => {
    requestConnectionDelete(() => performEdgeDelete(edgeId));
  }, [performEdgeDelete]);

  // Add event listener for node deletion
  useEffect(() => {
    const handleDeleteNode = (event) => {
      const { nodeId } = event.detail;
      handleNodeDelete(nodeId);
    };

    window.addEventListener("deleteNode", handleDeleteNode);

    return () => {
      window.removeEventListener("deleteNode", handleDeleteNode);
    };
  }, [handleNodeDelete]);

  // Add keyboard event listener for edge deletion
  useEffect(() => {
    const handleKeyDown = (event) => {
      // Delete or Backspace key
      if (
        (event.key === "Delete" || event.key === "Backspace") &&
        selectedEdge
      ) {
        event.preventDefault();
        handleEdgeDelete(selectedEdge.id);
      }
    };

    document.addEventListener("keydown", handleKeyDown);
    return () => {
      document.removeEventListener("keydown", handleKeyDown);
    };
  }, [selectedEdge, handleEdgeDelete]);

  const fetchTemplates = async () => {
    try {
      setLoading(true);
      const response = await axios.get(
        builderConfig.builderDataUrl || `/templateflowlist/gettemplatesforchatbot/${uid}`
      );
      if (response.data.message === "success") {
        setTemplates(response.data.templates);
        setInteractiveMessages(response.data.interactiveMessages || []);
        setAiBots(response.data.aiBots || []);
      }
    } catch (error) {
      console.error("Error fetching templates:", error);
      message.error("Failed to fetch templates");
    } finally {
      setLoading(false);
    }
  };

  const fetchAutomationBotData = async () => {
    try {
      setLoading(true);
      const response = await axios.get(
        builderConfig.builderDataUrl || `/templateflowlist/gettemplatesforchatbot/${uid}`
      );

      if (response.data.aiBots) {
        setAiBots(response.data.aiBots);
      }

      if (
        response.data.automationBot &&
        response.data.automationBot.exported_data
      ) {
        const exportedData = response.data.automationBot.exported_data;
        console.log("Loaded automation bot data:", exportedData);

        // Parse the exported data
        let parsedData;
        try {
          parsedData =
            typeof exportedData === "string"
              ? JSON.parse(exportedData)
              : exportedData;
        } catch (error) {
          console.error("Error parsing exported data:", error);
          return;
        }

        // Convert old drawflow format to ReactFlow format
        if (
          parsedData.drawflow &&
          parsedData.drawflow.Home &&
          parsedData.drawflow.Home.data
        ) {
          // Old drawflow format - convert to ReactFlow
          const convertedData = convertDrawflowToReactFlow(
            parsedData.drawflow.Home.data
          );
          setNodes(convertedData.nodes);
          setEdges(convertedData.edges.map(decorateEdge));
          console.log("Converted drawflow to ReactFlow:", convertedData);
        } else if (parsedData.nodes && parsedData.edges) {
          // Already in ReactFlow format
          setNodes(parsedData.nodes);
          setEdges(parsedData.edges.map(decorateEdge));
          console.log("Loaded ReactFlow data:", parsedData);
        } else {
          console.warn("Unknown data format, starting with empty canvas");
        }
      } else {
        console.warn(
          "No automation bot data found, starting with empty canvas"
        );
      }
    } catch (error) {
      console.error("Error fetching automation bot data:", error);
      message.error("Failed to load automation bot data");
    } finally {
      setLoading(false);
    }
  };

  const convertDrawflowToReactFlow = (drawflowData) => {
    const nodes = [];
    const edges = [];
    const nodeIdMapping = {};

    // Convert nodes
    Object.keys(drawflowData).forEach((oldNodeId, index) => {
      const oldNode = drawflowData[oldNodeId];
      const newNodeId = `node-${index}`;
      nodeIdMapping[oldNodeId] = newNodeId;

      // Determine node type and position
      let nodeType = "default";
      let position = { x: index * 200, y: index * 100 };

      if (oldNode.data && oldNode.data.templateId) {
        // Template message node
        nodeType = "templateMessage";
        position = { x: index * 250, y: index * 150 };
      } else if (oldNode.class === "customClass") {
        // Welcome message node
        nodeType = "welcomeMessage";
        position = { x: 0, y: 0 };
      } else if (oldNode.class === "custom-condition-node") {
        nodeType = "enhancedCondition";
        position = { x: index * 250, y: index * 150 };
      } else if (oldNode.class === "wait-for-response-node") {
        nodeType = "waitForResponse";
        position = { x: index * 250, y: index * 150 };
      } else if (oldNode.class === "delay-node") {
        nodeType = "delay";
        position = { x: index * 250, y: index * 150 };
      } else if (oldNode.class === "media-node") {
        nodeType = "mediaMessage";
        position = { x: index * 250, y: index * 150 };
      }

      const newNode = {
        id: newNodeId,
        type: nodeType,
        position,
        data: {
          ...oldNode.data,
          label: oldNode.data?.label || nodeType,
        },
      };

      nodes.push(newNode);
    });

    // Convert edges
    Object.keys(drawflowData).forEach((oldNodeId) => {
      const oldNode = drawflowData[oldNodeId];
      const sourceNodeId = nodeIdMapping[oldNodeId];

      if (oldNode.outputs) {
        Object.keys(oldNode.outputs).forEach((outputKey) => {
          const output = oldNode.outputs[outputKey];
          if (output.connections) {
            output.connections.forEach((connection, index) => {
              const targetNodeId = nodeIdMapping[connection.node];
              if (targetNodeId) {
                const edgeId = `edge-${sourceNodeId}-${targetNodeId}-${index}`;
                const edge = {
                  id: edgeId,
                  source: sourceNodeId,
                  target: targetNodeId,
                  type: outputKey.startsWith("reply-")
                    ? "quickReply"
                    : "default",
                  data: {
                    label: outputKey.startsWith("reply-")
                      ? "Quick Reply"
                      : "Default",
                    replyId: outputKey.replace("reply-", ""),
                  },
                  style: {
                    stroke: outputKey.startsWith("reply-")
                      ? "#52c41a"
                      : "#faad14",
                    strokeWidth: 2,
                  },
                };
                edges.push(edge);
              }
            });
          }
        });
      }
    });

    return { nodes, edges };
  };

  const onConnect = useCallback(
    (params) => {
      if (!params?.source || !params?.target || params.source === params.target) {
        return;
      }

      console.log("Connection attempt:", params);
      const sourceNode = nodes.find((node) => node.id === params.source);
      console.log("Source node:", sourceNode);

      // Check for existing connections from the same source handle to the same target
      const existingConnection = edges.find(
        (edge) =>
          edge.source === params.source &&
          edge.target === params.target &&
          edge.sourceHandle === params.sourceHandle
      );

      if (existingConnection) {
        console.warn(
          "Connection already exists from this handle to this target:",
          existingConnection
        );
        // Remove the existing connection first
        setEdges((eds) =>
          eds.filter((edge) => edge.id !== existingConnection.id)
        );
      }
      const isQuickReplyConnection =
        (sourceNode?.type === "templateMessage" ||
          sourceNode?.type === "welcomeMessage") &&
        sourceNode?.data?.quickReplies?.length > 0 &&
        params.sourceHandle &&
        params.sourceHandle.startsWith("reply-");

      // Check if this is a delivery status connection
      const isDeliveryStatusConnection =
        (sourceNode?.type === "templateMessage" ||
          sourceNode?.type === "welcomeMessage" ||
          sourceNode?.type === "interactiveMessage" ||
          sourceNode?.type === "carouselTemplate" ||
          sourceNode?.type === "whatsappFlowTemplate") &&
        params.sourceHandle &&
        (params.sourceHandle === "unread" ||
          params.sourceHandle === "undelivered");

      // Check if this is an interactive message connection
      const isInteractiveConnection =
        sourceNode?.type === "interactiveMessage" &&
        (sourceNode?.data?.interactiveType === "list" ||
          sourceNode?.data?.interactiveType === "button" ||
          sourceNode?.data?.interactiveType === "product" ||
          sourceNode?.data?.interactiveType === "product_list" ||
          sourceNode?.data?.interactiveType === "catalog_message" ||
          sourceNode?.data?.interactiveType === "cta_url" ||
          sourceNode?.data?.interactiveType === "flow" ||
          sourceNode?.data?.interactiveType === "location_request" ||
          sourceNode?.data?.interactiveType === "address") &&
        params.sourceHandle &&
        (params.sourceHandle.startsWith("interactive-") ||
          params.sourceHandle.startsWith("button-") ||
          params.sourceHandle === "default");

      // Check if this is a carousel template connection
      const isCarouselConnection =
        sourceNode?.type === "carouselTemplate" &&
        sourceNode?.data?.templateCards?.length > 0 &&
        params.sourceHandle &&
        (params.sourceHandle.startsWith("carousel-") ||
          params.sourceHandle === "delivered" ||
          params.sourceHandle === "unread" ||
          params.sourceHandle === "undelivered");

      if (isQuickReplyConnection) {
        // Get the quick reply data
        const replyId = params.sourceHandle.replace("reply-", "");
        const quickReply = sourceNode.data.quickReplies.find(
          (reply) => reply.id === replyId
        );

        const newEdge = {
          ...params,
          type: "quickReply",
          data: {
            label: quickReply ? quickReply.text : "Quick Reply",
            replyId: replyId,
            replyText: quickReply ? quickReply.text : "",
          },
          style: { stroke: "#52c41a", strokeWidth: 2 },
        };

        setEdges((eds) => addEdge(decorateEdge(newEdge), eds));
      } else if (isDeliveryStatusConnection) {
        // Handle delivery status connections
        const nodeType = sourceNode.type;
        const status = params.sourceHandle;
        let label = "";

        if (status === "unread") {
          switch (nodeType) {
            case "welcomeMessage":
              label = "Welcome Unread";
              break;
            case "templateMessage":
              label = "Template Unread";
              break;
            case "interactiveMessage":
              const interactiveType = sourceNode.data.interactiveType;
              switch (interactiveType) {
                case "product":
                  label = "Product Unread";
                  break;
                case "product_list":
                  label = "Products Unread";
                  break;
                case "catalog_message":
                  label = "Catalog Unread";
                  break;
                case "cta_url":
                  label = "CTA Unread";
                  break;
                case "flow":
                  label = "Flow Unread";
                  break;
                case "location_request":
                  label = "Location Request Unread";
                  break;
                case "address":
                  label = "Address Request Unread";
                  break;
                default:
                  label = "Message Unread";
              }
              break;
            case "carouselTemplate":
              label = "Carousel Unread";
              break;
            default:
              label = "Message Unread";
          }
        } else if (status === "undelivered") {
          switch (nodeType) {
            case "welcomeMessage":
              label = "Welcome Failed";
              break;
            case "templateMessage":
              label = "Template Failed";
              break;
            case "interactiveMessage":
              const interactiveType = sourceNode.data.interactiveType;
              switch (interactiveType) {
                case "product":
                  label = "Product Failed";
                  break;
                case "product_list":
                  label = "Products Failed";
                  break;
                case "catalog_message":
                  label = "Catalog Failed";
                  break;
                case "cta_url":
                  label = "CTA Failed";
                  break;
                case "flow":
                  label = "Flow Failed";
                  break;
                case "location_request":
                  label = "Location Request Failed";
                  break;
                case "address":
                  label = "Address Request Failed";
                  break;
                default:
                  label = "Message Failed";
              }
              break;
            case "carouselTemplate":
              label = "Carousel Failed";
              break;
            case "whatsappFlowTemplate":
              label = "WhatsApp Flow Failed";
              break;
            default:
              label = "Message Failed";
          }
        }

        const newEdge = {
          ...params,
          type: "quickReply",
          data: {
            label: label,
            replyId: `${status}-${nodeType}`,
            replyText: label,
          },
          style: {
            stroke:
              status === "delivered"
                ? "#52c41a"
                : status === "unread"
                  ? "#fa8c16"
                  : "#f5222d",
            strokeWidth: 2,
          },
        };

        setEdges((eds) => addEdge(decorateEdge(newEdge), eds));
      } else if (isCarouselConnection) {
        let edgeData = {};

        if (params.sourceHandle.startsWith("carousel-")) {
          // Handle carousel card Quick Reply button interactions
          const [cardIndex, buttonIndex] = params.sourceHandle
            .replace("carousel-", "")
            .split("-")
            .map(Number);

          const card = sourceNode.data.templateCards[cardIndex];
          const button = card?.buttons[buttonIndex];

          // Only process Quick Reply buttons
          if (button && button.type === "QUICK_REPLY") {
            edgeData = {
              label: button.text || "Carousel Quick Reply",
              replyId: `carousel-${cardIndex}-${buttonIndex}`,
              replyText: button.text || "",
            };
          } else {
            // Skip non-Quick Reply buttons (URL, PHONE_NUMBER)
            return;
          }
        } else if (params.sourceHandle === "unread") {
          // Handle unread status (timeout)
          edgeData = {
            label: "Carousel Unread",
            replyId: "unread-carousel",
            replyText: "Carousel Unread",
          };
        } else if (params.sourceHandle === "undelivered") {
          // Handle undelivered status (delivery failure)
          edgeData = {
            label: "Carousel Failed",
            replyId: "undelivered-carousel",
            replyText: "Carousel Failed",
          };
        }

        const newEdge = {
          ...params,
          type: "quickReply",
          data: edgeData,
          style: {
            stroke:
              params.sourceHandle === "delivered"
                ? "#52c41a"
                : params.sourceHandle === "unread"
                  ? "#fa8c16"
                  : params.sourceHandle === "undelivered"
                    ? "#f5222d"
                    : "#1890ff",
            strokeWidth: 2,
          },
        };

        setEdges((eds) => addEdge(decorateEdge(newEdge), eds));
      } else if (isInteractiveConnection) {
        let edgeData = {};

        if (params.sourceHandle.startsWith("interactive-")) {
          // Handle list message row interactions
          const [sectionIndex, rowIndex] = params.sourceHandle
            .replace("interactive-", "")
            .split("-")
            .map(Number);

          const section = sourceNode.data.sections[sectionIndex];
          const row = section?.rows[rowIndex];

          edgeData = {
            label: row ? row.title || row.id : "List Option",
            replyId: `list-${sectionIndex}-${rowIndex}`,
            replyText: row ? row.title || row.id : "",
          };
        } else if (params.sourceHandle.startsWith("button-")) {
          // Handle button message interactions
          const buttonIndex = params.sourceHandle.replace("button-", "");
          const button = sourceNode.data.buttons[buttonIndex];

          edgeData = {
            label: button ? button.title : "Button Option",
            replyId: `button-${buttonIndex}`,
            replyText: button ? button.title : "",
          };
        } else if (params.sourceHandle === "unread") {
          // Handle unread status (timeout)
          const interactiveType = sourceNode.data.interactiveType;
          let label = "Message Unread";

          switch (interactiveType) {
            case "product":
              label = "Product Unread";
              break;
            case "product_list":
              label = "Products Unread";
              break;
            case "catalog_message":
              label = "Catalog Unread";
              break;
            case "cta_url":
              label = "CTA Unread";
              break;
            case "flow":
              label = "Flow Unread";
              break;
            case "location_request":
              label = "Location Request Unread";
              break;
            case "address":
              label = "Address Request Unread";
              break;
            default:
              label = "Message Unread";
          }

          edgeData = {
            label: label,
            replyId: `unread-${interactiveType}`,
            replyText: label,
          };
        } else if (params.sourceHandle === "undelivered") {
          // Handle undelivered status (delivery failure)
          const interactiveType = sourceNode.data.interactiveType;
          let label = "Message Failed";

          switch (interactiveType) {
            case "product":
              label = "Product Failed";
              break;
            case "product_list":
              label = "Products Failed";
              break;
            case "catalog_message":
              label = "Catalog Failed";
              break;
            case "cta_url":
              label = "CTA Failed";
              break;
            case "flow":
              label = "Flow Failed";
              break;
            case "location_request":
              label = "Location Request Failed";
              break;
            case "address":
              label = "Address Request Failed";
              break;
            default:
              label = "Message Failed";
          }

          edgeData = {
            label: label,
            replyId: `undelivered-${interactiveType}`,
            replyText: label,
          };
        } else if (params.sourceHandle === "default") {
          // Handle default flow for non-interactive messages
          const interactiveType = sourceNode.data.interactiveType;
          let label = "Continue";

          switch (interactiveType) {
            case "product":
              label = "Product Sent";
              break;
            case "product_list":
              label = "Products Sent";
              break;
            case "catalog_message":
              label = "Catalog Sent";
              break;
            case "cta_url":
              label = "CTA Sent";
              break;
            case "flow":
              label = "Flow Sent";
              break;
            case "location_request":
              label = "Location Requested";
              break;
            case "address":
              label = "Address Requested";
              break;
            default:
              label = "Continue";
          }

          edgeData = {
            label: label,
            replyId: `default-${interactiveType}`,
            replyText: label,
          };
        }

        const newEdge = {
          ...params,
          type: "quickReply",
          data: edgeData,
          style: {
            stroke:
              params.sourceHandle === "unread"
                ? "#fa8c16"
                : params.sourceHandle === "undelivered"
                  ? "#f5222d"
                  : params.sourceHandle === "default"
                    ? "#faad14"
                    : "#1890ff",
            strokeWidth: 2,
          },
        };

        setEdges((eds) => addEdge(decorateEdge(newEdge), eds));
      } else {
        // Regular connection
        console.log(
          "Creating regular connection for:",
          sourceNode?.type || "unknown"
        );

        // Special handling for custom text nodes
        const isCustomTextNode =
          (sourceNode?.type === "welcomeMessage" &&
            sourceNode?.data?.messageType === "text") ||
          (sourceNode?.type === "templateMessage" &&
            sourceNode?.data?.messageType === "custom");

        if (isCustomTextNode && params.sourceHandle === "output_1") {
          console.log("🔗 Creating connection for custom text node");
        }

        // Ensure the connection has proper data even if handles are null
        const connectionData = {
          ...params,
          sourceHandle: params.sourceHandle || "default",
          targetHandle: params.targetHandle || "default",
          type: "smoothstep", // Explicitly set the edge type
          data: {
            label: "",
            replyId: "default",
            replyText: "Default",
          },
          style: {
            stroke: "#22c55e",
            strokeWidth: 2.5,
          },
        };

        console.log("🔗 Creating connection data:", connectionData);
        setEdges((eds) => {
          const newEdges = addEdge(decorateEdge(connectionData), eds);
          console.log("🔗 Updated edges:", newEdges);

          // Force a re-render of the ReactFlow component
          setTimeout(() => {
            setEdges((currentEdges) => [...currentEdges]);
          }, 100);

          return newEdges;
        });

        // Show success message for connection
        console.log("✅ Connection created successfully:", {
          from: params.source,
          to: params.target,
          handle: params.sourceHandle,
        });
      }
    },
    [setEdges, nodes, edges]
  );

  const onDragOver = useCallback((event) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = "move";
  }, []);

  const onDrop = useCallback(
    (event) => {
      event.preventDefault();

      const type = event.dataTransfer.getData("application/reactflow");
      const nodeType = event.dataTransfer.getData("nodeType");

      if (typeof type === "undefined" || !type || !reactFlowInstance) {
        return;
      }

      const position = reactFlowInstance.screenToFlowPosition({
        x: event.clientX,
        y: event.clientY,
      });

      const newNode = {
        id: `${nodeType}-${Date.now()}`,
        type: nodeType,
        position,
        data: { label: `${nodeType} node` },
      };

      console.log("New node created:", newNode.id, newNode.type);
      setNodes((nds) => nds.concat(newNode));
    },
    [reactFlowInstance, setNodes]
  );

  const onNodeClick = useCallback((event, node) => {
    console.log("Node clicked:", node.id, node.type, node.data);
    setSelectedNode(node);
    setCurrentConfiguringNodeId(node.id);
    // Open appropriate module based on node type
    switch (node.type) {
      case "welcomeMessage":
        setWelcomeMessageModule(true);
        break;
      case "typingIndicator":
        setTypingIndicatorModule(true);
        break;
      case "enhancedCondition":
        setEnhancedConditionModule(true);
        break;
      case "httpRequest":
        setHttpRequestModule(true);
        break;
      case "jumpToStep":
        setJumpToStepModule(true);
        break;
      case "functionCall":
        setFunctionCallModule(true);
        break;
      case "dateTimeCondition":
        setDateTimeConditionModule(true);
        break;
      case "waitForResponse":
        setWaitForResponseModule(true);
        break;
      case "naturalLanguage":
        setNaturalLanguageModule(true);
        break;
      case "templateMessage":
        setTemplateMessageModule(true);
        break;
      case "interactiveMessage":
        setInteractiveMessageModule(true);
        break;
      case "carouselTemplate":
        setCarouselTemplateModule(true);
        break;
      case "whatsappFlowTemplate":
        setWhatsappFlowTemplateModule(true);
        break;
      case "delay":
        setDelayModule(true);
        break;
      case "mediaMessage":
        setMediaModule(true);
        break;
      default:
        break;
    }
  }, []);

  // Edge click handler
  const onEdgeClick = useCallback(
    (event, edge) => {
      event.preventDefault();
      setSelectedEdge(edge);

      requestConnectionDelete(() => performEdgeDelete(edge.id)).then((confirmed) => {
        if (!confirmed) {
          setSelectedEdge(null);
        }
      });
    },
    [performEdgeDelete]
  );

  const handleWelcomeMessageSave = (data, nodeId = null) => {
    console.log("handleWelcomeMessageSave called with data:", data);
    console.log("Quick replies in save data:", data.quickReplies);
    console.log(
      "Node ID to update:",
      nodeId || currentConfiguringNodeId || selectedNode?.id
    );

    const targetNodeId = nodeId || currentConfiguringNodeId || selectedNode?.id;

    if (targetNodeId) {
      // Update existing node
      setNodes((nds) =>
        nds.map((node) =>
          node.id === targetNodeId
            ? {
              ...node,
              data: { ...node.data, ...data },
              // Force re-render by updating the key
              key: `welcome-${Date.now()}`,
            }
            : node
        )
      );
      console.log("Updated existing welcome node:", targetNodeId);
    } else {
      // Create new node
      const newNode = {
        id: `welcomeMessage-${Date.now()}`,
        type: "welcomeMessage",
        position: { x: 100, y: 100 },
        data: data,
      };
      setNodes((nds) => nds.concat(newNode));
      console.log("Created new welcome node:", newNode.id);
    }

    // Force a re-render of the ReactFlow component
    setTimeout(() => {
      setNodes((nds) => [...nds]);
    }, 100);
  };

  const handleTypingIndicatorSave = (data, nodeId = null) => {
    const targetNodeId = nodeId || currentConfiguringNodeId || selectedNode?.id;

    if (targetNodeId) {
      // Update existing node
      setNodes((nds) =>
        nds.map((node) =>
          node.id === targetNodeId
            ? { ...node, data: { ...node.data, ...data } }
            : node
        )
      );
    } else {
      // Create new node
      const newNode = {
        id: `typingIndicator-${Date.now()}`,
        type: "typingIndicator",
        position: { x: 100, y: 200 },
        data: data,
      };
      setNodes((nds) => nds.concat(newNode));
    }
  };

  const handleDelaySave = (data) => {
    if (selectedNode) {
      // Update existing node
      setNodes((nds) =>
        nds.map((node) =>
          node.id === selectedNode.id
            ? { ...node, data: { ...node.data, ...data } }
            : node
        )
      );
    } else {
      // Create new node
      const newNode = {
        id: `delay-${Date.now()}`,
        type: "delay",
        position: { x: 100, y: 300 },
        data: data,
      };
      setNodes((nds) => nds.concat(newNode));
    }
  };

  const handleMediaSave = (data, nodeId = null) => {
    if (selectedNode || nodeId) {
      // Update existing node
      const targetNodeId = nodeId || selectedNode.id;
      setNodes((nds) =>
        nds.map((node) =>
          node.id === targetNodeId
            ? { ...node, data: { ...node.data, ...data } }
            : node
        )
      );
    } else {
      // Create new node
      const newNode = {
        id: `media-${Date.now()}`,
        type: "mediaMessage",
        position: { x: 100, y: 300 },
        data: data,
      };
      setNodes((nds) => nds.concat(newNode));
    }
  };

  const handleEnhancedConditionSave = (data) => {
    if (selectedNode) {
      // Update existing node
      setNodes((nds) =>
        nds.map((node) =>
          node.id === selectedNode.id
            ? { ...node, data: { ...node.data, ...data } }
            : node
        )
      );
    } else {
      // Create new node
      const newNode = {
        id: `enhancedCondition-${Date.now()}`,
        type: "enhancedCondition",
        position: { x: 100, y: 400 },
        data: data,
      };
      setNodes((nds) => nds.concat(newNode));
    }
  };

  const handleHttpRequestSave = (data) => {
    if (selectedNode) {
      // Update existing node
      setNodes((nds) =>
        nds.map((node) =>
          node.id === selectedNode.id
            ? { ...node, data: { ...node.data, ...data } }
            : node
        )
      );
    } else {
      // Create new node
      const newNode = {
        id: `httpRequest-${Date.now()}`,
        type: "httpRequest",
        position: { x: 100, y: 500 },
        data: data,
      };
      setNodes((nds) => nds.concat(newNode));
    }
  };

  const handleJumpToStepSave = (data, nodeId = null) => {
    const targetNodeId = nodeId || currentConfiguringNodeId || selectedNode?.id;

    if (targetNodeId) {
      // Update existing node
      setNodes((nds) =>
        nds.map((node) =>
          node.id === targetNodeId
            ? { ...node, data: { ...node.data, ...data } }
            : node
        )
      );
    } else {
      // Create new node
      const newNode = {
        id: `jumpToStep-${Date.now()}`,
        type: "jumpToStep",
        position: { x: 100, y: 600 },
        data: data,
      };
      setNodes((nds) => nds.concat(newNode));
    }
  };

  const handleDateTimeConditionSave = (data, nodeId = null) => {
    const targetNodeId = nodeId || currentConfiguringNodeId || selectedNode?.id;

    if (targetNodeId) {
      // Update existing node
      setNodes((nds) =>
        nds.map((node) =>
          node.id === targetNodeId
            ? { ...node, data: { ...node.data, ...data } }
            : node
        )
      );
    } else {
      // Create new node
      const newNode = {
        id: `dateTimeCondition-${Date.now()}`,
        type: "dateTimeCondition",
        position: { x: 100, y: 600 },
        data: data,
      };
      setNodes((nds) => nds.concat(newNode));
    }
  };

  const handleNaturalLanguageSave = (data, nodeId = null) => {
    const targetNodeId = nodeId || currentConfiguringNodeId || selectedNode?.id;

    if (targetNodeId) {
      // Update existing node
      setNodes((nds) =>
        nds.map((node) =>
          node.id === targetNodeId
            ? { ...node, data: { ...node.data, ...data } }
            : node
        )
      );
    } else {
      // Create new node
      const newNode = {
        id: `naturalLanguage-${Date.now()}`,
        type: "naturalLanguage",
        position: { x: 100, y: 600 },
        data: data,
      };
      setNodes((nds) => nds.concat(newNode));
    }
  };

  const handleFunctionCallSave = (data) => {
    if (selectedNode) {
      // Update existing node
      setNodes((nds) =>
        nds.map((node) =>
          node.id === selectedNode.id
            ? { ...node, data: { ...node.data, ...data } }
            : node
        )
      );
    } else {
      // Create new node
      const newNode = {
        id: `functionCall-${Date.now()}`,
        type: "functionCall",
        position: { x: 100, y: 700 },
        data: data,
      };
      setNodes((nds) => nds.concat(newNode));
    }
  };

  const handleTemplateMessageSave = (data, nodeId = null) => {
    console.log("handleTemplateMessageSave called with data:", data);
    console.log("Quick replies in save data:", data.quickReplies);
    console.log(
      "Node ID to update:",
      nodeId || currentConfiguringNodeId || selectedNode?.id
    );

    const targetNodeId = nodeId || currentConfiguringNodeId || selectedNode?.id;

    if (targetNodeId) {
      // Update existing node
      setNodes((nds) =>
        nds.map((node) =>
          node.id === targetNodeId
            ? {
              ...node,
              data: { ...node.data, ...data },
              // Force re-render by updating the key
              key: `template-${Date.now()}`,
            }
            : node
        )
      );
      console.log("Updated existing node:", targetNodeId);
    } else {
      // Create new node
      const newNode = {
        id: `templateMessage-${Date.now()}`,
        type: "templateMessage",
        position: { x: 100, y: 900 },
        data: data,
      };
      setNodes((nds) => nds.concat(newNode));
      console.log("Created new node:", newNode.id);
    }

    // Force a re-render of the ReactFlow component
    setTimeout(() => {
      setNodes((nds) => [...nds]);
    }, 100);
  };

  const handleInteractiveMessageSave = (data, nodeId = null) => {
    console.log("handleInteractiveMessageSave called with data:", data);
    console.log(
      "Node ID to update:",
      nodeId || currentConfiguringNodeId || selectedNode?.id
    );

    const targetNodeId = nodeId || currentConfiguringNodeId || selectedNode?.id;

    if (targetNodeId) {
      // Update existing node
      setNodes((nds) =>
        nds.map((node) =>
          node.id === targetNodeId
            ? { ...node, data: { ...node.data, ...data } }
            : node
        )
      );
      console.log("Updated existing interactive node:", targetNodeId);
    } else {
      // Create new node
      const newNode = {
        id: `interactiveMessage-${Date.now()}`,
        type: "interactiveMessage",
        position: { x: 100, y: 1000 },
        data: data,
      };
      setNodes((nds) => nds.concat(newNode));
      console.log("Created new interactive node:", newNode.id);
    }
  };

  const handleCarouselTemplateSave = (data, nodeId = null) => {
    console.log("handleCarouselTemplateSave called with data:", data);
    console.log("Template cards in save data:", data.templateCards);
    console.log(
      "Node ID to update:",
      nodeId || currentConfiguringNodeId || selectedNode?.id
    );

    const targetNodeId = nodeId || currentConfiguringNodeId || selectedNode?.id;

    if (targetNodeId) {
      // Update existing node
      setNodes((nds) =>
        nds.map((node) =>
          node.id === targetNodeId
            ? {
              ...node,
              data: { ...node.data, ...data },
              // Force re-render by updating the key
              key: `carousel-${Date.now()}`,
            }
            : node
        )
      );
      console.log("Updated existing carousel node:", targetNodeId);
    } else {
      // Create new node
      const newNode = {
        id: `carouselTemplate-${Date.now()}`,
        type: "carouselTemplate",
        position: { x: 100, y: 1100 },
        data: data,
      };
      setNodes((nds) => nds.concat(newNode));
      console.log("Created new carousel node:", newNode.id);
    }

    // Force a re-render of the ReactFlow component
    setTimeout(() => {
      setNodes((nds) => [...nds]);
    }, 100);
  };

  const handleWhatsappFlowTemplateSave = (data, nodeId = null) => {
    console.log("handleWhatsappFlowTemplateSave called with data:", data);
    console.log(
      "Node ID to update:",
      nodeId || currentConfiguringNodeId || selectedNode?.id
    );

    const targetNodeId = nodeId || currentConfiguringNodeId || selectedNode?.id;

    if (targetNodeId) {
      // Update existing node
      setNodes((nds) =>
        nds.map((node) =>
          node.id === targetNodeId
            ? {
              ...node,
              data: { ...node.data, ...data },
              // Force re-render by updating the key
              key: `whatsapp-flow-${Date.now()}`,
            }
            : node
        )
      );
      console.log(
        "Updated existing WhatsApp Flow Template node:",
        targetNodeId
      );
    } else {
      // Create new node
      const newNode = {
        id: `whatsappFlowTemplate-${Date.now()}`,
        type: "whatsappFlowTemplate",
        position: { x: 100, y: 1200 },
        data: data,
      };
      setNodes((nds) => nds.concat(newNode));
      console.log("Created new WhatsApp Flow Template node:", newNode.id);
    }

    // Force a re-render of the ReactFlow component
    setTimeout(() => {
      setNodes((nds) => [...nds]);
    }, 100);
  };

  const handleWaitForResponseSave = (data, nodeId = null) => {
    console.log("handleWaitForResponseSave called with data:", data);
    console.log(
      "Node ID to update:",
      nodeId || currentConfiguringNodeId || selectedNode?.id
    );

    const targetNodeId = nodeId || currentConfiguringNodeId || selectedNode?.id;

    if (targetNodeId) {
      // Update existing node
      setNodes((nds) =>
        nds.map((node) =>
          node.id === targetNodeId
            ? {
              ...node,
              data: { ...node.data, ...data },
              // Force re-render by updating the key
              key: `wait-for-response-${Date.now()}`,
            }
            : node
        )
      );
      console.log("Updated existing Wait for Response node:", targetNodeId);
    } else {
      // Create new node
      const newNode = {
        id: `waitForResponse-${Date.now()}`,
        type: "waitForResponse",
        position: { x: 100, y: 1400 },
        data: data,
      };
      setNodes((nds) => nds.concat(newNode));
      console.log("Created new Wait for Response node:", newNode.id);
    }

    // Force a re-render of the ReactFlow component
    setTimeout(() => {
      setNodes((nds) => [...nds]);
    }, 100);
  };

  const onDragStart = useCallback((event, nodeType) => {
    event.dataTransfer.setData("application/reactflow", nodeType);
    event.dataTransfer.setData("nodeType", nodeType);
    event.dataTransfer.effectAllowed = "move";

    if (event.dataTransfer.setDragImage && event.currentTarget instanceof HTMLElement) {
      event.dataTransfer.setDragImage(event.currentTarget, 10, 10);
    }
  }, []);

  const saveFlow = async () => {
    try {
      setSaveLoading(true);
      const flowData = {
        nodes,
        edges,
        viewport: reactFlowInstance.getViewport(),
      };

      const response = await axios.post(
        builderConfig.saveUrl || `/templateflowlist/chatbotflowsave/${uid}`,
        {
          customData: JSON.stringify(flowData),
        }
      );

      if (response.data.status === "success" || response.data.success === true) {
        try {
          await axios.post(builderConfig.clearCacheUrl || `/templateflowlist/clearchatbotcache/${uid}`);
          console.log("Cache cleared successfully for customer:", uid);
        } catch (cacheError) {
          console.warn(
            "Failed to clear cache, but flow was saved:",
            cacheError
          );
        }

        message.success("Flow saved and cache cleared successfully!");

        if (!builderConfig.isActive && builderConfig.toggleUrl) {
          const enableChatbot = await confirmChatbotAction({
            title: "Enable chatbot?",
            message:
              "Your flow has been saved. Do you want to enable this chatbot now?",
            confirmLabel: "Enable",
            variant: "default",
          });

          if (enableChatbot) {
            try {
              const toggleResponse = await axios.patch(builderConfig.toggleUrl);
              if (toggleResponse.data?.success) {
                builderConfig.isActive = true;
                if (window.__CHATBOT_BUILDER_CONFIG__) {
                  window.__CHATBOT_BUILDER_CONFIG__.isActive = true;
                }
                message.success("Chatbot enabled successfully!");
              }
            } catch (toggleError) {
              const toggleMessage =
                toggleError.response?.data?.errors?.flow?.[0] ||
                toggleError.response?.data?.message ||
                "Unable to enable chatbot.";

              await alertChatbotAction(toggleMessage, "Enable chatbot");
            }
          }
        }
      } else {
        message.error("Failed to save flow");
      }
    } catch (error) {
      console.error("Error saving flow:", error);
      message.error("Error saving flow");
    } finally {
      setSaveLoading(false);
    }
  };

  const exportFlow = () => {
    if (reactFlowInstance) {
      const flow = reactFlowInstance.toObject();
      const blob = new Blob([JSON.stringify(flow, null, 2)], {
        type: "application/json",
      });
      const url = URL.createObjectURL(blob);
      const linkElement = document.createElement("a");
      linkElement.href = url;
      linkElement.setAttribute("download", `chatbot-flow-${Date.now()}.json`);
      linkElement.click();
      URL.revokeObjectURL(url);
    }
  };

  const importFlow = (event) => {
    const file = event.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (e) => {
        try {
          const flow = JSON.parse(e.target.result);
          setNodes(flow.nodes || []);
          setEdges((flow.edges || []).map(decorateEdge));
          if (flow.viewport && reactFlowInstance) {
            reactFlowInstance.setViewport(flow.viewport);
          }
          message.success("Flow imported successfully!");
        } catch (error) {
          message.error("Invalid flow file");
        }
      };
      reader.readAsText(file);
    }
  };

  const builderActionsRef = useRef({ saveFlow, exportFlow, importFlow });
  builderActionsRef.current = { saveFlow, exportFlow, importFlow };

  useEffect(() => {
    const onSave = () => builderActionsRef.current.saveFlow();
    const onExport = () => builderActionsRef.current.exportFlow();
    const onImport = (event) => {
      if (event.detail?.input) {
        builderActionsRef.current.importFlow(event.detail.input);
      }
    };

    window.addEventListener("chatbot:save-flow", onSave);
    window.addEventListener("chatbot:export-flow", onExport);
    window.addEventListener("chatbot:import-flow", onImport);

    return () => {
      window.removeEventListener("chatbot:save-flow", onSave);
      window.removeEventListener("chatbot:export-flow", onExport);
      window.removeEventListener("chatbot:import-flow", onImport);
    };
  }, []);

  useEffect(() => {
    window.dispatchEvent(
      new CustomEvent("chatbot:save-state", {
        detail: { saving: saveLoading },
      })
    );
  }, [saveLoading]);

  // Sidebar menu items
  const sidebarItems = [
    {
      key: "messages",
      icon: <MessageOutlined />,
      label: "Messages",
      children: [
        {
          key: "welcomeMessage",
          icon: <HomeOutlined />,
          label: "Welcome Message",
          onDragStart: (e) => onDragStart(e, "welcomeMessage"),
          draggable: true,
        },
        {
          key: "templateMessage",
          icon: <FileTextOutlined />,
          label: "Template Message",
          onDragStart: (e) => onDragStart(e, "templateMessage"),
          draggable: true,
        },
        {
          key: "interactiveMessage",
          icon: <MessageOutlined />,
          label: "Interactive Message",
          onDragStart: (e) => onDragStart(e, "interactiveMessage"),
          draggable: true,
        },
        {
          key: "carouselTemplate",
          icon: <CarOutlined />,
          label: "Carousel Template",
          onDragStart: (e) => onDragStart(e, "carouselTemplate"),
          draggable: true,
        },
        {
          key: "whatsappFlowTemplate",
          icon: <ApiOutlined />,
          label: "WhatsApp Flow Template",
          onDragStart: (e) => onDragStart(e, "whatsappFlowTemplate"),
          draggable: true,
        },
        {
          key: "mediaMessage",
          icon: <UploadOutlined />,
          label: "Media Message",
          onDragStart: (e) => onDragStart(e, "mediaMessage"),
          draggable: true,
        },
        {
          key: "typingIndicator",
          icon: <LoadingOutlined />,
          label: "Typing Indicator",
          onDragStart: (e) => onDragStart(e, "typingIndicator"),
          draggable: true,
        },
      ],
    },
    {
      key: "logic",
      icon: <BranchesOutlined />,
      label: "Logic & Control",
      children: [
        {
          key: "enhancedCondition",
          icon: <BranchesOutlined />,
          label: "Enhanced Condition",
          onDragStart: (e) => onDragStart(e, "enhancedCondition"),
          draggable: true,
        },
        {
          key: "dateTimeCondition",
          icon: <CalendarOutlined />,
          label: "Business Hours & Time Branching",
          onDragStart: (e) => onDragStart(e, "dateTimeCondition"),
          draggable: true,
        },
        {
          key: "jumpToStep",
          icon: <ThunderboltOutlined />,
          label: "Jump to Step / Loop",
          onDragStart: (e) => onDragStart(e, "jumpToStep"),
          draggable: true,
        },
      ],
    },
    {
      key: "integration",
      icon: <ApiOutlined />,
      label: "Integration",
      children: [
        {
          key: "httpRequest",
          icon: <ApiOutlined />,
          label: "HTTP Request",
          onDragStart: (e) => onDragStart(e, "httpRequest"),
          draggable: true,
        },
        {
          key: "functionCall",
          icon: <FunctionOutlined />,
          label: "Function Call",
          onDragStart: (e) => onDragStart(e, "functionCall"),
          draggable: true,
        },
      ],
    },
    {
      key: "ai",
      icon: <RobotOutlined />,
      label: "AI & Intelligence",
      children: [
        {
          key: "naturalLanguage",
          icon: <RobotOutlined />,
          label: "Natural Language (AI)",
          onDragStart: (e) => onDragStart(e, "naturalLanguage"),
          draggable: true,
        },
      ],
    },
    {
      key: "timing",
      icon: <ClockCircleOutlined />,
      label: "Timing",
      children: [
        {
          key: "delay",
          icon: <ClockCircleOutlined />,
          label: "Delay",
          onDragStart: (e) => onDragStart(e, "delay"),
          draggable: true,
        },
        {
          key: "waitForResponse",
          icon: <QuestionCircleOutlined />,
          label: (
            <span>
              Wait for Response
              {/* <span
                style={{
                  marginLeft: 6,
                  background: "#fff1f0",
                  color: "#cf1322",
                  border: "1px solid #ffa39e",
                  borderRadius: 4,
                  padding: "0 6px",
                  fontSize: 10,
                  fontWeight: 600,
                }}
              >
                Coming Soon
              </span> */}
            </span>
          ),
          // onClick: () => {
          //   message.warning("Wait for Response is coming soon!");
          // },
          onDragStart: (e) => onDragStart(e, "waitForResponse"),
          draggable: true,
          // style: { opacity: 0.6, cursor: "not-allowed" },
        },
      ],
    },
  ];

  return (
    <div className="chatbot-flow-builder">
      <div className="chatbot-flow-toolbar">
        <div className="chatbot-flow-toolbar__row">
          <NodeCategoryToolbar categories={sidebarItems} />
        </div>
      </div>

      <div className="chatbot-flow-canvas">
        <ReactFlow
          nodes={nodes}
          edges={edges}
          onNodesChange={onNodesChange}
          onEdgesChange={onEdgesChange}
          onConnect={onConnect}
          onInit={setReactFlowInstance}
          onDrop={onDrop}
          onDragOver={onDragOver}
          onNodeClick={onNodeClick}
          onEdgeClick={onEdgeClick}
          onPaneClick={() => setSelectedEdge(null)}
          nodeTypes={nodeTypes}
          edgeTypes={edgeTypes}
          nodesConnectable
          elementsSelectable
          elevateEdgesOnSelect
          elevateNodesOnSelect={false}
          connectionMode={ConnectionMode.Loose}
          connectionRadius={28}
          connectionLineType="smoothstep"
          defaultEdgeOptions={{
            type: "smoothstep",
            zIndex: 1000,
            style: { stroke: "#22c55e", strokeWidth: 2.5 },
            markerEnd: {
              type: MarkerType.ArrowClosed,
              color: "#22c55e",
              width: 18,
              height: 18,
            },
          }}
          connectionLineStyle={{ stroke: "#22c55e", strokeWidth: 3, strokeDasharray: "5,5" }}
          isValidConnection={(connection) =>
            Boolean(connection.source) &&
            Boolean(connection.target) &&
            connection.source !== connection.target
          }
          fitView
          attributionPosition="bottom-left"
        >
            <Controls />
            <Background variant="dots" gap={20} size={1} color="#e5e5e5" />
            <MiniMap />

            {selectedEdge && (
              <Panel position="top-right">
                <Space>
                  <div
                    style={{
                      background: "#e6f7ff",
                      padding: "8px 12px",
                      borderRadius: "4px",
                      boxShadow: "0 2px 8px rgba(0,0,0,0.1)",
                      border: "1px solid #91d5ff",
                      fontSize: "12px",
                    }}
                  >
                    <BranchesOutlined style={{ color: "#1890ff" }} /> Selected
                    Connection
                  </div>
                  <Button
                    type="primary"
                    danger
                    size="small"
                    icon={<DeleteOutlined />}
                    onClick={() => handleEdgeDelete(selectedEdge.id)}
                    title="Delete selected connection"
                  >
                    Delete Connection
                  </Button>
                </Space>
              </Panel>
            )}
          </ReactFlow>
      </div>

      {/* Module Drawers */}
      <ReactFlowWelcomeMessageModule
        key={`welcome-${currentConfiguringNodeId}`}
        visible={welcomeMessageModule}
        onClose={() => {
          setWelcomeMessageModule(false);
          setCurrentConfiguringNodeId(null);
        }}
        onSave={(data) =>
          handleWelcomeMessageSave(data, currentConfiguringNodeId)
        }
        templates={templates}
        nodeData={selectedNode?.data}
        variables={globalVariables}
      />

      <ReactFlowTypingIndicatorModule
        key={`typing-${currentConfiguringNodeId}`}
        visible={typingIndicatorModule}
        onClose={() => {
          setTypingIndicatorModule(false);
          setCurrentConfiguringNodeId(null);
        }}
        onSave={(data) => handleTypingIndicatorSave(data, currentConfiguringNodeId)}
        nodeData={selectedNode?.data}
      />

      <ReactFlowDelayModule
        visible={delayModule}
        onClose={() => setDelayModule(false)}
        onSave={handleDelaySave}
        nodeData={selectedNode?.data}
      />

      <ReactFlowMediaModule
        key={`media-${currentConfiguringNodeId}`}
        visible={mediaModule}
        onClose={() => {
          setMediaModule(false);
          setCurrentConfiguringNodeId(null);
        }}
        onSave={(data) => handleMediaSave(data, currentConfiguringNodeId)}
        nodeData={selectedNode?.data}
        variables={globalVariables}
      />

      <ReactFlowEnhancedConditionModule
        visible={enhancedConditionModule}
        onClose={() => setEnhancedConditionModule(false)}
        onSave={handleEnhancedConditionSave}
        nodeData={selectedNode?.data}
        variables={globalVariables}
        templates={templates}
        availableSteps={nodes.map((node) => ({
          id: node.id,
          name: node.data?.label || node.type,
          type: node.type,
        }))}
      />

      <ReactFlowHttpRequestModule
        visible={httpRequestModule}
        onClose={() => setHttpRequestModule(false)}
        onSave={handleHttpRequestSave}
        nodeData={selectedNode?.data}
      />

      <ReactFlowJumpToStepModule
        key={`jump-${currentConfiguringNodeId}`}
        visible={jumpToStepModule}
        onClose={() => {
          setJumpToStepModule(false);
          setCurrentConfiguringNodeId(null);
        }}
        onSave={(data) => handleJumpToStepSave(data, currentConfiguringNodeId)}
        nodeData={selectedNode?.data}
        availableSteps={nodes.map((node) => ({
          id: node.id,
          name: node.data?.label || node.data?.text || node.type,
          type: node.type,
        }))}
      />

      <ReactFlowFunctionCallModule
        visible={functionCallModule}
        onClose={() => setFunctionCallModule(false)}
        onSave={handleFunctionCallSave}
        nodeData={selectedNode?.data}
      />

      <ReactFlowDateTimeConditionModule
        key={`datetime-${currentConfiguringNodeId}`}
        visible={dateTimeConditionModule}
        onClose={() => {
          setDateTimeConditionModule(false);
          setCurrentConfiguringNodeId(null);
        }}
        onSave={(data) => handleDateTimeConditionSave(data, currentConfiguringNodeId)}
        nodeData={selectedNode?.data}
      />

      <ReactFlowWaitForResponseModule
        key={`wait-for-response-${currentConfiguringNodeId}`}
        visible={waitForResponseModule}
        onClose={() => {
          setWaitForResponseModule(false);
          setCurrentConfiguringNodeId(null);
        }}
        onSave={(data) =>
          handleWaitForResponseSave(data, currentConfiguringNodeId)
        }
        nodeData={selectedNode?.data}
        variables={globalVariables}
      />

      <ReactFlowNaturalLanguageModule
        key={`natural-language-${currentConfiguringNodeId}`}
        visible={naturalLanguageModule}
        onClose={() => {
          setNaturalLanguageModule(false);
          setCurrentConfiguringNodeId(null);
        }}
        onSave={(data) =>
          handleNaturalLanguageSave(data, currentConfiguringNodeId)
        }
        nodeData={selectedNode?.data}
        aiBots={aiBots}
        variables={globalVariables}
      />

      <ReactFlowTemplateMessageModule
        key={`template-${currentConfiguringNodeId}`}
        visible={templateMessageModule}
        onClose={() => {
          setTemplateMessageModule(false);
          setCurrentConfiguringNodeId(null);
        }}
        onSave={(data) =>
          handleTemplateMessageSave(data, currentConfiguringNodeId)
        }
        templates={templates}
        nodeData={selectedNode?.data}
        variables={globalVariables}
        availableSteps={nodes.map((node) => ({
          id: node.id,
          name: node.data?.label || node.type,
          type: node.type,
        }))}
      />

      <ReactFlowInteractiveMessageModule
        key={`interactive-${currentConfiguringNodeId}`}
        visible={interactiveMessageModule}
        onClose={() => {
          setInteractiveMessageModule(false);
          setCurrentConfiguringNodeId(null);
        }}
        onSave={(data) =>
          handleInteractiveMessageSave(data, currentConfiguringNodeId)
        }
        nodeData={selectedNode?.data}
        variables={globalVariables}
        interactiveMessages={interactiveMessages}
      />

      <ReactFlowCarouselTemplateModule
        key={`carousel-${currentConfiguringNodeId}`}
        visible={carouselTemplateModule}
        onClose={() => {
          setCarouselTemplateModule(false);
          setCurrentConfiguringNodeId(null);
        }}
        onSave={(data) =>
          handleCarouselTemplateSave(data, currentConfiguringNodeId)
        }
        templates={templates}
        nodeData={selectedNode?.data}
        variables={globalVariables}
      />

      <ReactFlowWhatsappFlowTemplateModule
        key={`whatsapp-flow-${currentConfiguringNodeId}`}
        visible={whatsappFlowTemplateModule}
        onClose={() => {
          setWhatsappFlowTemplateModule(false);
          setCurrentConfiguringNodeId(null);
        }}
        onSave={(data) =>
          handleWhatsappFlowTemplateSave(data, currentConfiguringNodeId)
        }
        templates={templates}
        nodeData={selectedNode?.data}
        variables={globalVariables}
      />

      <ReactFlowDelayModule
        key={`delay-${currentConfiguringNodeId}`}
        visible={delayModule}
        onClose={() => {
          setDelayModule(false);
          setCurrentConfiguringNodeId(null);
        }}
        onSave={(data) => handleDelaySave(data, currentConfiguringNodeId)}
        nodeData={selectedNode?.data}
      />
      {/* AI Flow Generator */}
      <AIChatbotGenerator
        visible={aiGeneratorVisible}
        onClose={() => setAiGeneratorVisible(false)}
        onApply={(flowData) => {
          setNodes(flowData.nodes);
          setEdges(flowData.edges.map(decorateEdge));
          message.success("AI Flow applied to canvas!");
        }}
      />
    </div>
  );
};

export default ChatBotFlowReactFlow;
