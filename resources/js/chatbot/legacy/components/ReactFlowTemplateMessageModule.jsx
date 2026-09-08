import React, { useState, useEffect } from "react";
import BuilderDrawer from "./BuilderDrawer.jsx";
import {
  Form,
  Card,
  Button,
  Space,
  Select,
  Typography,
  message,
  Radio,
  Divider,
  Tag,
  Tooltip,
  Input,
  Row,
  Col,
  List,
  Modal,
  Switch,
  Tabs,
  Collapse,
  Badge,
} from "antd";
import {
  FileTextOutlined,
  PictureOutlined,
  SendOutlined,
  UploadOutlined,
  EyeOutlined,
  CopyOutlined,
  BranchesOutlined,
  PlusOutlined,
  DeleteOutlined,
  EditOutlined,
  MessageOutlined,
  CheckCircleOutlined,
} from "@ant-design/icons";
import VariableHelper from "./VariableHelper.jsx";

const { Text, Paragraph } = Typography;
const { TextArea } = Input;
const { TabPane } = Tabs;
const { Panel } = Collapse;

const ReactFlowTemplateMessageModule = ({
  visible,
  onClose,
  onSave,
  templates = [],
  nodeData = null,
  variables = [],
  availableSteps = [],
}) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [selectedTemplate, setSelectedTemplate] = useState(null);
  const [customText, setCustomText] = useState("");
  const [quickReplies, setQuickReplies] = useState([]);
  const [showBranchingConfig, setShowBranchingConfig] = useState(false);

  // Timeout configuration state
  const [timeoutConfig, setTimeoutConfig] = useState({
    unreadTimeout: 300, // 5 minutes default
    undeliveredTimeout: 60, // 1 minute default
  });

  // Initialize form with node data if editing
  useEffect(() => {
    if (visible && nodeData) {
      form.setFieldsValue(nodeData);
      if (nodeData.templateId) {
        const template = templates.find((t) => t.id === nodeData.templateId);
        if (template) {
          setSelectedTemplate(template);
          // Extract quick replies from template based on button_type and auto_reply_text fields
          if (template.button_type == 2 || template.button_type == 4) {
            const quickReplyTexts = [];
            // Check auto_reply_text_1 to auto_reply_text_10
            for (let i = 1; i <= 10; i++) {
              const replyText = template[`auto_reply_text_${i}`];
              if (replyText && replyText.trim() !== "") {
                quickReplyTexts.push({
                  id: `reply_${i - 1}`,
                  text: replyText.trim(),
                  action: "reply",
                  value: replyText.trim(),
                  targetNode:
                    nodeData.quickReplyBranches?.[replyText.trim()] || "",
                });
              }
            }
            setQuickReplies(quickReplyTexts);
          }
        }
      }
      if (nodeData.customText) {
        setCustomText(nodeData.customText);
      }
      if (nodeData.quickReplyBranches) {
        setShowBranchingConfig(true);
      }
      if (nodeData.timeoutConfig) {
        setTimeoutConfig(nodeData.timeoutConfig);
      }
    } else if (visible) {
      // Reset all form fields and state for new node
      form.resetFields();
      setSelectedTemplate(null);
      setCustomText("");
      setQuickReplies([]);
      setShowBranchingConfig(false);
      setTimeoutConfig({
        unreadTimeout: 300,
        undeliveredTimeout: 60,
      });
      form.setFieldsValue({
        messageType: "template",
        templateId: "",
        customText: "",
        sendImmediately: true,
        fallbackText: "",
        quickReplyBranches: {},
        defaultBranch: "",
        timeoutConfig: {
          deliveredTimeout: 0,
          unreadTimeout: 300,
          undeliveredTimeout: 60,
        },
      });
    }
  }, [visible, nodeData, form, templates]);

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      console.log("handleSave - form values:", values);
      console.log("handleSave - selectedTemplate:", selectedTemplate);
      console.log("handleSave - quickReplies:", quickReplies);

      // Process the form data
      const nodeData = {
        ...values,
        selectedTemplate: selectedTemplate,
        quickReplies: quickReplies,
        timeoutConfig: {
          unreadTimeout: timeoutConfig.unreadTimeout,
          undeliveredTimeout: timeoutConfig.undeliveredTimeout,
        },
        // Ensure templateId is set if we have a selected template
        templateId: selectedTemplate ? selectedTemplate.id : values.templateId,
        label:
          values.messageType === "template"
            ? `Template Message - ${
                selectedTemplate?.template_name || "Template"
              }`
            : "Text Message",
      };

      console.log("handleSave - final nodeData:", nodeData);
      console.log(
        "handleSave - quickReplies in nodeData:",
        nodeData.quickReplies
      );

      onSave(nodeData);
      message.success("Template message configuration saved successfully!");
      onClose();
    } catch (error) {
      console.error("Form validation failed:", error);
      message.error("Failed to save configuration. Please check the form.");
    } finally {
      setLoading(false);
    }
  };

  const handleVariableSelect = (variableSyntax, fieldName) => {
    const currentValue = form.getFieldValue(fieldName) || "";
    form.setFieldsValue({ [fieldName]: currentValue + variableSyntax });
  };

  const handleTemplateChange = (templateId) => {
    console.log("handleTemplateChange called with templateId:", templateId);
    const template = templates.find((t) => t.id === templateId);
    console.log("Found template:", template);

    if (template) {
      setSelectedTemplate(template);
      console.log("Template button_type:", template.button_type);

      // Extract quick replies from template based on button_type and auto_reply_text fields
      if (template.button_type == 2 || template.button_type == 4) {
        const quickReplyTexts = [];
        // Check auto_reply_text_1 to auto_reply_text_10
        for (let i = 1; i <= 10; i++) {
          const replyText = template[`auto_reply_text_${i}`];
          console.log(`auto_reply_text_${i}:`, replyText);
          if (replyText && replyText.trim() !== "") {
            quickReplyTexts.push({
              id: `reply_${i - 1}`,
              text: replyText.trim(),
              action: "reply",
              value: replyText.trim(),
              targetNode: "",
            });
          }
        }

        console.log("Extracted quick replies:", quickReplyTexts);
        if (quickReplyTexts.length > 0) {
          setQuickReplies(quickReplyTexts);
          setShowBranchingConfig(true);
          console.log("Set quick replies and show branching config");
        } else {
          setQuickReplies([]);
          setShowBranchingConfig(false);
          console.log("No quick replies found, hiding branching config");
        }
      } else {
        setQuickReplies([]);
        setShowBranchingConfig(false);
        console.log("Template is not a quick reply template");
      }
    }
  };

  const copyTemplateText = () => {
    if (selectedTemplate?.actual_body) {
      navigator.clipboard.writeText(selectedTemplate.actual_body);
      message.success("Template text copied to clipboard!");
    }
  };

  const getTemplateTypeColor = (template) => {
    const colors = {
      text: "blue",
      image: "green",
      video: "purple",
      document: "orange",
      audio: "red",
    };
    return colors[template.template_type] || "default";
  };

  const getTemplateTypeLabel = (template) => {
    const labels = {
      text: "Text",
      image: "Image",
      video: "Video",
      document: "Document",
      audio: "Audio",
    };
    return labels[template.template_type] || "Unknown";
  };

  const renderQuickReplyInfo = () => {
    if (!showBranchingConfig || quickReplies.length === 0) {
      return null;
    }

    return (
      <Card
        title="Quick Reply Branching"
        size="small"
        style={{ marginBottom: 16 }}
      >
        <div style={{ marginBottom: 16 }}>
          <Text type="secondary" style={{ fontSize: "12px", marginBottom: 8 }}>
            This template has {quickReplies.length} quick reply buttons. Connect
            them directly on the canvas by dragging from the green handles below
            the node.
          </Text>
        </div>

        <div style={{ marginBottom: 12 }}>
          <Text strong>Available Quick Replies:</Text>
          <div style={{ marginTop: 8 }}>
            {quickReplies.map((reply, index) => (
              <Tag key={index} color="blue" style={{ margin: 2 }}>
                {reply.text}
              </Tag>
            ))}
          </div>
        </div>

        <div
          style={{
            background: "#f6ffed",
            border: "1px solid #b7eb8f",
            borderRadius: 4,
            padding: 12,
            fontSize: "12px",
          }}
        >
          <Text strong>Canvas Branching Instructions:</Text>
          <ul style={{ margin: "8px 0 0 0", paddingLeft: 16 }}>
            <li>Green handles below the node represent quick reply buttons</li>
            <li>Drag from these handles to connect to target nodes</li>
            <li>
              Yellow handle in the center is for the default branch (no
              response)
            </li>
            <li>Connections will be labeled with the quick reply text</li>
          </ul>
        </div>
      </Card>
    );
  };

  return (
    <BuilderDrawer
      title={
        <div className="chatbot-builder-drawer__title">
          <span className="chatbot-builder-drawer__title-icon">
            <FileTextOutlined />
          </span>
          <span className="chatbot-builder-drawer__title-text">
            Template Message Configuration
          </span>
          <Tag color="green">
            {nodeData?.messageType === "template" ? "Template" : "Custom Text"}
          </Tag>
        </div>
      }
      width={800}
      open={visible}
      onClose={onClose}
      footer={
        <Space style={{ width: "100%", justifyContent: "flex-end" }}>
          <Button onClick={onClose}>Cancel</Button>
          <Button
            type="primary"
            icon={<SendOutlined />}
            loading={loading}
            onClick={() => {
              console.log("Save button clicked!");
              handleSave();
            }}
            style={{ minWidth: 120 }}
          >
            Save Configuration
          </Button>
        </Space>
      }
    >
      <Form form={form} layout="vertical">
        {/* Message Type Selection */}
        <Card title="Message Type" size="small" style={{ marginBottom: 16 }}>
          <Form.Item name="messageType" initialValue="template">
            <Radio.Group>
              <Space direction="vertical" style={{ width: "100%" }}>
                <Radio.Button value="template">
                  <Space>
                    <FileTextOutlined />
                    Use Pre-built Template
                  </Space>
                </Radio.Button>
                <Radio.Button value="custom">
                  <Space>
                    <FileTextOutlined />
                    Custom Text Message
                  </Space>
                </Radio.Button>
              </Space>
            </Radio.Group>
          </Form.Item>
        </Card>

        {/* Dynamic Form Based on Message Type */}
        <Form.Item
          noStyle
          shouldUpdate={(prevValues, currentValues) =>
            prevValues.messageType !== currentValues.messageType
          }
        >
          {({ getFieldValue }) => {
            const messageType = getFieldValue("messageType");
            if (messageType === "template") {
              return (
                <>
                  {/* Template Selection */}
                  <Card
                    title="Select Template"
                    size="small"
                    style={{ marginBottom: 16 }}
                  >
                    <Form.Item
                      name="templateId"
                      rules={[
                        { required: true, message: "Please select a template" },
                      ]}
                    >
                      <Select
                        placeholder="Choose a template from your library"
                        showSearch
                        optionFilterProp="children"
                        onChange={handleTemplateChange}
                        filterOption={(input, option) => {
                          // Get the template name from the option's data attribute
                          const templateName =
                            option["data-template-name"] || "";
                          return (
                            templateName
                              .toLowerCase()
                              .indexOf(input.toLowerCase()) >= 0
                          );
                        }}
                      >
                        {templates
                          .filter(
                            (template) =>
                              template.is_carousel_template !== 1 &&
                              (template.button_text_flow === null ||
                                template.button_text_flow === "")
                          ) // Exclude carousel templates and WhatsApp Flow templates - they have their own dedicated nodes
                          .map((template) => (
                            <Select.Option
                              key={template.id}
                              value={template.id}
                              data-template-name={template.template_name}
                            >
                              <div>
                                <div style={{ fontWeight: 500 }}>
                                  {template.template_name}
                                </div>
                                <div
                                  style={{ fontSize: "12px", color: "#666" }}
                                >
                                  {template.actual_body?.substring(0, 80)}...
                                </div>
                                <div style={{ fontSize: "11px", marginTop: 4 }}>
                                  <Tag
                                    size="small"
                                    color={getTemplateTypeColor(template)}
                                  >
                                    {getTemplateTypeLabel(template)}
                                  </Tag>
                                  {template.category && (
                                    <Tag size="small" color="default">
                                      {template.category}
                                    </Tag>
                                  )}
                                  {(template.button_type == 2 ||
                                    template.button_type == 4) && (
                                    <Tag size="small" color="green">
                                      Quick Reply Template
                                    </Tag>
                                  )}
                                </div>
                              </div>
                            </Select.Option>
                          ))}
                      </Select>
                    </Form.Item>

                    {/* Template Preview */}
                    {selectedTemplate && (
                      <Card size="small" style={{ marginTop: 16 }}>
                        <div style={{ marginBottom: 8 }}>
                          <Space>
                            <Text strong>Template Preview</Text>
                            <Button
                              type="text"
                              size="small"
                              icon={<CopyOutlined />}
                              onClick={copyTemplateText}
                            >
                              Copy Text
                            </Button>
                          </Space>
                        </div>
                        <div
                          style={{
                            background: "#f5f5f5",
                            padding: 12,
                            borderRadius: 4,
                            fontSize: "14px",
                            lineHeight: 1.5,
                          }}
                        >
                          {selectedTemplate.actual_body}
                        </div>
                        {(selectedTemplate.button_type == 2 ||
                          selectedTemplate.button_type == 4) &&
                          quickReplies.length > 0 && (
                            <div style={{ marginTop: 12 }}>
                              <Text strong>Quick Reply Buttons:</Text>
                              <div style={{ marginTop: 4 }}>
                                {quickReplies.map((reply, index) => (
                                  <Tag
                                    key={index}
                                    color="blue"
                                    style={{ margin: 2 }}
                                  >
                                    {reply.text}
                                  </Tag>
                                ))}
                              </div>
                            </div>
                          )}
                      </Card>
                    )}
                  </Card>

                  {/* Quick Reply Branching Information */}
                  {renderQuickReplyInfo()}
                </>
              );
            }
            if (messageType === "custom") {
              return (
                <Card
                  title="Custom Text Message"
                  size="small"
                  style={{ marginBottom: 16 }}
                >
                  <Form.Item
                    name="customText"
                    rules={[
                      { required: true, message: "Please enter message text" },
                    ]}
                  >
                    <div>
                      <TextArea
                        rows={6}
                        placeholder="Enter your custom message text... You can use variables like {{user_name}}, {{order_id}}, etc."
                        value={customText}
                        onChange={(e) => setCustomText(e.target.value)}
                        showCount
                        maxLength={1000}
                      />
                      <div style={{ marginTop: 8 }}>
                        <VariableHelper
                          variables={variables}
                          onVariableSelect={handleVariableSelect}
                          placeholder="Insert Variable"
                          size="small"
                        />
                      </div>
                    </div>
                  </Form.Item>

                  {/* Custom Text Preview */}
                  {customText && (
                    <Card size="small" style={{ marginTop: 16 }}>
                      <div style={{ marginBottom: 8 }}>
                        <Text strong>Message Preview</Text>
                      </div>
                      <div
                        style={{
                          background: "#f5f5f5",
                          padding: 12,
                          borderRadius: 4,
                          fontSize: "14px",
                          lineHeight: 1.5,
                        }}
                      >
                        {customText}
                      </div>
                    </Card>
                  )}
                </Card>
              );
            }
            return null;
          }}
        </Form.Item>

        {/* Template Information */}
        {selectedTemplate && (
          <Card
            title="Template Information"
            size="small"
            style={{ marginBottom: 16 }}
          >
            <Row gutter={16}>
              <Col span={12}>
                <div style={{ marginBottom: 8 }}>
                  <Text strong>Template Name:</Text>
                  <div>{selectedTemplate.template_name}</div>
                </div>
              </Col>
              <Col span={12}>
                <div style={{ marginBottom: 8 }}>
                  <Text strong>Category:</Text>
                  <div>{selectedTemplate.category || "N/A"}</div>
                </div>
              </Col>
            </Row>
            <Row gutter={16}>
              <Col span={12}>
                <div style={{ marginBottom: 8 }}>
                  <Text strong>Language:</Text>
                  <div>{selectedTemplate.language || "Default"}</div>
                </div>
              </Col>
              <Col span={12}>
                <div style={{ marginBottom: 8 }}>
                  <Text strong>Status:</Text>
                  <div>
                    <Tag
                      color={
                        selectedTemplate.status === "APPROVED"
                          ? "green"
                          : "orange"
                      }
                    >
                      {selectedTemplate.status}
                    </Tag>
                  </div>
                </div>
              </Col>
            </Row>
          </Card>
        )}

        {/* Timeout Configuration */}

        {/* Usage Guidelines */}
        <Card title="Usage Guidelines" size="small">
          <div style={{ fontSize: "12px", color: "#666" }}>
            <Paragraph style={{ marginBottom: 8 }}>
              <Text strong>WhatsApp Template Messages:</Text>
            </Paragraph>
            <ul style={{ margin: 0, paddingLeft: 16 }}>
              <li>Templates must be pre-approved by WhatsApp</li>
              <li>
                Variables must be properly formatted: {"{{variable_name}}"}
              </li>
              <li>
                Quick reply buttons create automatic branching in the flow
              </li>
              <li>Each quick reply can route to different steps</li>
              <li>
                Always provide a default branch for users who don't respond
              </li>
              <li>Set appropriate timeout for user responses</li>
            </ul>

            <Divider style={{ margin: "12px 0" }} />

            <Paragraph style={{ marginBottom: 8 }}>
              <Text strong>Canvas-Based Quick Reply Branching:</Text>
            </Paragraph>
            <ul style={{ margin: 0, paddingLeft: 16 }}>
              <li>
                Quick reply buttons appear as green handles below the template
                node
              </li>
              <li>
                Drag from these handles to connect to target nodes on the canvas
              </li>
              <li>
                Yellow center handle represents the default branch (no response)
              </li>
              <li>
                Connections are automatically labeled with the quick reply text
              </li>
              <li>
                Branching is visual and intuitive - no complex configuration
                needed
              </li>
            </ul>
          </div>
        </Card>
      </Form>
    </BuilderDrawer>
  );
};

export default ReactFlowTemplateMessageModule;
