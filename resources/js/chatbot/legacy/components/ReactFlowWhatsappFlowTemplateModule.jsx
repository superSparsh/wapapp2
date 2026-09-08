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
  Input,
  Row,
  Col,
  List,
  Modal,
  Switch,
  Tabs,
  Collapse,
  Badge,
  Divider,
  Tag,
  Tooltip,
  Spin,
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
  ApiOutlined,
  LinkOutlined,
  PhoneOutlined,
} from "@ant-design/icons";
import VariableHelper from "./VariableHelper.jsx";
import axios from "axios";

const { Text, Paragraph } = Typography;
const { TextArea } = Input;
const { TabPane } = Tabs;
const { Panel } = Collapse;

const ReactFlowWhatsappFlowTemplateModule = ({
  visible,
  onClose,
  onSave,
  templates = [],
  nodeData = null,
  variables = [],
}) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [selectedTemplate, setSelectedTemplate] = useState(null);

  // Timeout configuration state
  const [timeoutConfig, setTimeoutConfig] = useState({
    unreadTimeout: 300, // 5 minutes default
    undeliveredTimeout: 60, // 1 minute default
  });

  // Interactive responses state
  const [interactiveResponses, setInteractiveResponses] = useState([]);

  // Initialize form with node data if editing
  useEffect(() => {
    if (visible && nodeData) {
      console.log("Editing existing node with data:", nodeData);

      // Set timeout config first
      if (nodeData.timeoutConfig) {
        setTimeoutConfig(nodeData.timeoutConfig);
      }

      // Find and set the selected template
      if (nodeData.templateId) {
        const template = templates.find((t) => t.id === nodeData.templateId);
        if (template) {
          setSelectedTemplate(template);
        }
      }

      // Set all form fields including timeout values
      const formValues = {
        ...nodeData,
        unreadTimeout: nodeData.timeoutConfig?.unreadTimeout || 300,
        undeliveredTimeout: nodeData.timeoutConfig?.undeliveredTimeout || 60,
      };

      // Load interactive responses if they exist
      if (nodeData.interactiveResponses) {
        setInteractiveResponses(nodeData.interactiveResponses);
      }

      console.log("Setting form values:", formValues);
      form.setFieldsValue(formValues);
    } else if (visible) {
      console.log("Creating new node - resetting form");
      // Reset all form fields and state for new node
      form.resetFields();
      setSelectedTemplate(null);
      setInteractiveResponses([]);
      setTimeoutConfig({
        unreadTimeout: 300,
        undeliveredTimeout: 60,
      });
      form.setFieldsValue({
        templateId: "",
        flowToken: "",
        flowAction: "continue",
        sendImmediately: true,
        fallbackText: "",
        unreadTimeout: 300,
        undeliveredTimeout: 60,
      });
    }
  }, [visible, nodeData, form, templates]);

  const handleTemplateChange = (templateId) => {
    const template = templates.find((t) => t.id === templateId);
    if (template) {
      setSelectedTemplate(template);
      form.setFieldsValue({
        templateId: templateId,
        flowToken: template.button_text_flow || "",
      });
    }
  };

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      console.log("handleSave - form values:", values);
      console.log("handleSave - selectedTemplate:", selectedTemplate);

      // Process the form data
      const nodeData = {
        ...values,
        selectedTemplate: selectedTemplate,
        timeoutConfig: {
          unreadTimeout: timeoutConfig.unreadTimeout,
          undeliveredTimeout: timeoutConfig.undeliveredTimeout,
        },
        interactiveResponses: interactiveResponses,
        templateId: selectedTemplate ? selectedTemplate.id : values.templateId,
        label: `WhatsApp Flow Template - ${
          selectedTemplate?.template_name || "Flow Template"
        }`,
      };

      console.log("handleSave - final nodeData:", nodeData);

      onSave(nodeData);
      message.success(
        "WhatsApp Flow Template configuration saved successfully!"
      );
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

  const copyTemplateText = () => {
    if (selectedTemplate?.actual_body) {
      navigator.clipboard.writeText(selectedTemplate.actual_body);
      message.success("Template text copied to clipboard!");
    }
  };

  const getTemplateTypeColor = (template) => {
    if (template.button_text_flow) return "purple";
    if (template.button_type == 2) return "green";
    return "blue";
  };

  const getTemplateTypeLabel = (template) => {
    if (template.button_text_flow) return "WhatsApp Flow";
    if (template.button_type == 2) return "Quick Reply";
    return "Regular";
  };

  return (
    <BuilderDrawer
      title={
        <div className="chatbot-builder-drawer__title">
          <span className="chatbot-builder-drawer__title-icon">
            <ApiOutlined />
          </span>
          <span className="chatbot-builder-drawer__title-text">
            WhatsApp Flow Template Configuration
          </span>
          <Tag color="green">WhatsApp Flow</Tag>
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
        {/* Template Selection */}
        <Card
          title="Select WhatsApp Flow Template"
          size="small"
          style={{ marginBottom: 16 }}
        >
          <Form.Item
            label="Select Template"
            name="templateId"
            rules={[
              {
                required: true,
                message: "Please select a WhatsApp Flow template",
              },
            ]}
          >
            <Select
              placeholder="Select a WhatsApp Flow template"
              onChange={handleTemplateChange}
              loading={loading}
              showSearch
              filterOption={(input, option) => {
                // Get the template name from the option's data attribute
                const templateName = option["data-template-name"] || "";
                return (
                  templateName.toLowerCase().indexOf(input.toLowerCase()) >= 0
                );
              }}
            >
              {templates
                .filter(
                  (template) =>
                    template.button_text_flow !== null &&
                    template.button_text_flow !== ""
                ) // Only show WhatsApp Flow templates
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
                      <div style={{ fontSize: "12px", color: "#666" }}>
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
                        <Tag size="small" color="purple">
                          WhatsApp Flow
                        </Tag>
                      </div>
                    </div>
                  </Select.Option>
                ))}
            </Select>
          </Form.Item>
        </Card>

        {/* Template Preview */}
        {selectedTemplate && (
          <Card
            title="Template Preview"
            size="small"
            style={{ marginBottom: 16 }}
          >
            <div style={{ marginBottom: 8 }}>
              <Space>
                <Text strong>Template Content</Text>
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
            {selectedTemplate.button_text_flow && (
              <div style={{ marginTop: 12 }}>
                <Text strong>WhatsApp Flow Token:</Text>
                <div style={{ marginTop: 4 }}>
                  <Tag color="purple" style={{ margin: 2 }}>
                    {selectedTemplate.button_text_flow}
                  </Tag>
                </div>
              </div>
            )}
          </Card>
        )}

        {/* Flow Configuration */}
        {selectedTemplate && (
          <Card
            title="WhatsApp Flow Configuration"
            size="small"
            style={{ marginBottom: 16 }}
          >
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item label="Flow Token" name="flowToken">
                  <Input
                    placeholder="Flow token from template"
                    disabled
                    addonBefore={<ApiOutlined />}
                  />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item label="Flow Action" name="flowAction">
                  <Select placeholder="Select flow action">
                    <Select.Option value="continue">
                      Continue Flow
                    </Select.Option>
                    <Select.Option value="end">End Flow</Select.Option>
                    <Select.Option value="restart">Restart Flow</Select.Option>
                  </Select>
                </Form.Item>
              </Col>
            </Row>

            <Form.Item
              label="Send Immediately"
              name="sendImmediately"
              valuePropName="checked"
            >
              <Switch checkedChildren="Yes" unCheckedChildren="No" />
            </Form.Item>

            <Form.Item
              label="Fallback Text"
              name="fallbackText"
              tooltip="Text to send if WhatsApp Flow is not available"
            >
              <TextArea
                rows={3}
                placeholder="Enter fallback text..."
                showCount
                maxLength={1000}
              />
            </Form.Item>
          </Card>
        )}

        {/* Timeout Configuration */}
        <Card
          title="Delivery Status Timeout Configuration"
          size="small"
          style={{ marginBottom: 16 }}
        >
          <div style={{ fontSize: "12px", color: "#666", marginBottom: 16 }}>
            Configure how long to wait before considering messages as "unread"
            or "undelivered"
          </div>

          <Row gutter={16}>
            <Col span={8}>
              <Form.Item label="Unread Timeout" name="unreadTimeout">
                <Input
                  type="number"
                  min={0}
                  placeholder="300"
                  addonAfter="seconds"
                  onChange={(e) => {
                    const value = parseInt(e.target.value) || 300;
                    setTimeoutConfig({
                      ...timeoutConfig,
                      unreadTimeout: value,
                    });
                  }}
                />
              </Form.Item>
              <div style={{ fontSize: "11px", color: "#999" }}>
                Time to wait before considering message unread (default: 5
                minutes)
              </div>
            </Col>
            <Col span={8}>
              <Form.Item label="Undelivered Timeout" name="undeliveredTimeout">
                <Input
                  type="number"
                  min={0}
                  placeholder="60"
                  addonAfter="seconds"
                  onChange={(e) => {
                    const value = parseInt(e.target.value) || 60;
                    setTimeoutConfig({
                      ...timeoutConfig,
                      undeliveredTimeout: value,
                    });
                  }}
                />
              </Form.Item>
              <div style={{ fontSize: "11px", color: "#999" }}>
                Time to wait before considering message undelivered (default: 1
                minute)
              </div>
            </Col>
          </Row>
        </Card>

        {/* Response Configuration */}
        <Card
          title="Response Handling"
          size="small"
          style={{ marginBottom: 16 }}
        >
          <Form.Item
            name="enableResponseMatching"
            valuePropName="checked"
            label="Enable Response Matching"
          >
            <Switch />
          </Form.Item>
          <div style={{ fontSize: "11px", color: "#999", marginBottom: 16 }}>
            Enable this to configure specific responses for different user
            interactions
          </div>

          <Form.Item
            name="fallbackAction"
            label="Fallback Action"
            initialValue="continue"
          >
            <Select>
              <Select.Option value="continue">
                Continue to Next Node
              </Select.Option>
              <Select.Option value="end">End Conversation</Select.Option>
            </Select>
          </Form.Item>
          <div style={{ fontSize: "11px", color: "#999" }}>
            Action to take when no specific response is matched
          </div>

          {/* Interactive Responses Configuration */}
          <Form.Item
            noStyle
            shouldUpdate={(prevValues, currentValues) =>
              prevValues.enableResponseMatching !==
              currentValues.enableResponseMatching
            }
          >
            {({ getFieldValue }) => {
              const enableResponseMatching = getFieldValue(
                "enableResponseMatching"
              );

              if (!enableResponseMatching) {
                return null;
              }

              return (
                <div style={{ marginTop: 16 }}>
                  <div style={{ marginBottom: 8 }}>
                    <Text strong>Interactive Responses:</Text>
                    <div style={{ fontSize: "11px", color: "#999" }}>
                      Configure specific responses for different user
                      interactions
                    </div>
                  </div>

                  {interactiveResponses.map((response, index) => (
                    <Card
                      key={index}
                      size="small"
                      style={{ marginBottom: 8 }}
                      title={`Response ${index + 1}`}
                      extra={
                        <Button
                          type="text"
                          size="small"
                          icon={<DeleteOutlined />}
                          onClick={() => {
                            const newResponses = [...interactiveResponses];
                            newResponses.splice(index, 1);
                            setInteractiveResponses(newResponses);
                          }}
                        />
                      }
                    >
                      <Row gutter={16}>
                        <Col span={12}>
                          <Form.Item
                            label="Response Text"
                            style={{ marginBottom: 8 }}
                          >
                            <Input
                              placeholder="e.g., Yes, No, Maybe"
                              value={response.text}
                              onChange={(e) => {
                                const newResponses = [...interactiveResponses];
                                newResponses[index].text = e.target.value;
                                setInteractiveResponses(newResponses);
                              }}
                            />
                          </Form.Item>
                        </Col>
                        <Col span={12}>
                          <Form.Item
                            label="Response ID"
                            style={{ marginBottom: 8 }}
                          >
                            <Input
                              placeholder="e.g., yes_button, no_button"
                              value={response.id}
                              onChange={(e) => {
                                const newResponses = [...interactiveResponses];
                                newResponses[index].id = e.target.value;
                                setInteractiveResponses(newResponses);
                              }}
                            />
                          </Form.Item>
                        </Col>
                      </Row>
                      <Form.Item label="Action" style={{ marginBottom: 8 }}>
                        <Select
                          value={response.action}
                          onChange={(value) => {
                            const newResponses = [...interactiveResponses];
                            newResponses[index].action = value;
                            setInteractiveResponses(newResponses);
                          }}
                        >
                          <Select.Option value="continue">
                            Continue to Next Node
                          </Select.Option>
                          <Select.Option value="end">
                            End Conversation
                          </Select.Option>
                        </Select>
                      </Form.Item>
                    </Card>
                  ))}

                  <Button
                    type="dashed"
                    icon={<PlusOutlined />}
                    onClick={() => {
                      setInteractiveResponses([
                        ...interactiveResponses,
                        { text: "", id: "", action: "continue" },
                      ]);
                    }}
                    style={{ width: "100%" }}
                  >
                    Add Interactive Response
                  </Button>
                </div>
              );
            }}
          </Form.Item>
        </Card>

        {/* Variables Helper */}
        <Card
          title="Variables Helper"
          size="small"
          style={{ marginBottom: 16 }}
        >
          <VariableHelper
            variables={variables}
            onVariableSelect={handleVariableSelect}
            targetField="fallbackText"
          />
        </Card>

        {/* Guidelines */}
        <Card title="WhatsApp Flow Template Guidelines" size="small">
          <div style={{ fontSize: "12px", color: "#666" }}>
            <Text strong>WhatsApp Flow Templates:</Text>
            <ul style={{ margin: "8px 0 0 0", paddingLeft: 16 }}>
              <li>
                WhatsApp Flow templates have a flow token in the
                button_text_flow field
              </li>
              <li>These templates can trigger WhatsApp Business API flows</li>
              <li>
                Flow actions determine what happens after the flow completes
              </li>
              <li>
                Always provide fallback text for cases where flows are
                unavailable
              </li>
              <li>Delivery status branches handle flow execution results</li>
            </ul>
          </div>
        </Card>

        {/* Canvas Branching Instructions */}
        <Card title="Canvas Branching Instructions" size="small">
          <div style={{ fontSize: "12px", color: "#666" }}>
            <Text strong>Delivery Status Branches:</Text>
            <ul style={{ margin: "8px 0 0 0", paddingLeft: 16 }}>
              <li>Green handle: Flow delivered successfully</li>
              <li>Orange handle: Flow delivered but not completed (timeout)</li>
              <li>Red handle: Flow delivery failed</li>
              <li>Drag from these handles to connect to target nodes</li>
              <li>Connections will be labeled with the delivery status</li>
            </ul>
          </div>
        </Card>
      </Form>
    </BuilderDrawer>
  );
};

export default ReactFlowWhatsappFlowTemplateModule;
