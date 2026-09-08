import React, { useState, useEffect } from "react";
import BuilderDrawer, { BuilderDrawerTitle } from "./BuilderDrawer.jsx";
import {
  Form,
  Radio,
  Input,
  Button,
  Space,
  message,
  Card,
  Select,
  Upload,
  Typography,
  Tag,
  Row,
  Col,
  Divider,
} from "antd";
import {
  HomeOutlined,
  SendOutlined,
  FileTextOutlined,
  PictureOutlined,
  UploadOutlined,
} from "@ant-design/icons";
import VariableHelper from "./VariableHelper.jsx";

const { TextArea } = Input;
const { Text, Paragraph } = Typography;

const ReactFlowWelcomeMessageModule = ({
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
      // Set selected template and quick replies if they exist
      if (nodeData.selectedTemplate) {
        setSelectedTemplate(nodeData.selectedTemplate);
        setQuickReplies(nodeData.quickReplies || []);
        setShowBranchingConfig(
          nodeData.selectedTemplate.button_type == 2 ||
            nodeData.selectedTemplate.button_type == 4
        );
      }
      if (nodeData.timeoutConfig) {
        setTimeoutConfig(nodeData.timeoutConfig);
      }
    } else if (visible) {
      // Reset all form fields and state for new node
      form.resetFields();
      form.setFieldsValue({
        messageType: "text",
        triggerKeyword: "",
        welcomeMessage: "",
      });
      setSelectedTemplate(null);
      setQuickReplies([]);
      setShowBranchingConfig(false);
      setTimeoutConfig({
        unreadTimeout: 300,
        undeliveredTimeout: 60,
      });
    }
  }, [visible, nodeData, form]);

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      console.log("Welcome message save values:", values);
      console.log("Selected template:", selectedTemplate);
      console.log("Quick replies:", quickReplies);

      // Process the form data
      const nodeData = {
        ...values,
        selectedTemplate: selectedTemplate,
        quickReplies: quickReplies, // Ensure quickReplies are passed
        templateId: selectedTemplate ? selectedTemplate.id : values.templateId, // Ensure templateId is set
        timeoutConfig: {
          unreadTimeout: timeoutConfig.unreadTimeout,
          undeliveredTimeout: timeoutConfig.undeliveredTimeout,
        },
        label: `Welcome Message - ${values.messageType}`,
        // Backward compatibility: include text field for existing nodes
        text: values.triggerKeyword || values.text || "",
      };

      console.log("Final node data to save:", nodeData);

      onSave(nodeData);
      message.success("Welcome message configured successfully!");
      onClose();
    } catch (error) {
      console.error("Form validation failed:", error);
      message.error("Failed to save configuration. Please check the form.");
    } finally {
      setLoading(false);
    }
  };

  const handleVariableSelect = (variableSyntax) => {
    const currentText = form.getFieldValue("welcomeMessage") || "";
    const newText = currentText + variableSyntax;
    form.setFieldsValue({ welcomeMessage: newText });
  };

  const handleTemplateChange = (templateId) => {
    console.log("Template changed to:", templateId);
    const template = templates.find((t) => t.id === templateId);
    setSelectedTemplate(template);

    if (template) {
      console.log("Template button_type:", template.button_type);
      console.log("Template auto_reply_text_1:", template.auto_reply_text_1);
      console.log("Template auto_reply_text_2:", template.auto_reply_text_2);
      console.log("Template auto_reply_text_3:", template.auto_reply_text_3);
      console.log("Template auto_reply_text_4:", template.auto_reply_text_4);
      console.log("Template auto_reply_text_5:", template.auto_reply_text_5);
      console.log("Template auto_reply_text_6:", template.auto_reply_text_6);
      console.log("Template auto_reply_text_7:", template.auto_reply_text_7);
      console.log("Template auto_reply_text_8:", template.auto_reply_text_8);
      console.log("Template auto_reply_text_9:", template.auto_reply_text_9);
      console.log("Template auto_reply_text_10:", template.auto_reply_text_10);

      // Extract quick replies from template
      const extractedQuickReplies = [];
      for (let i = 1; i <= 10; i++) {
        const replyText = template[`auto_reply_text_${i}`];
        if (replyText && replyText.trim() !== "") {
          extractedQuickReplies.push({
            id: i,
            text: replyText.trim(),
          });
        }
      }

      console.log("Extracted quick replies:", extractedQuickReplies);
      setQuickReplies(extractedQuickReplies);
      setShowBranchingConfig(
        template.button_type == 2 || template.button_type == 4
      );
      console.log("Set quick replies and show branching config");
    } else {
      setQuickReplies([]);
      setShowBranchingConfig(false);
    }
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
            borderRadius: "4px",
            padding: "12px",
            fontSize: "12px",
          }}
        >
          <Text strong>Canvas Branching Instructions:</Text>
          <ul style={{ margin: "8px 0 0 0", paddingLeft: "20px" }}>
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
        <BuilderDrawerTitle icon={<HomeOutlined />}>
          Welcome Message Configuration
        </BuilderDrawerTitle>
      }
      width={480}
      open={visible}
      onClose={onClose}
      footer={
        <Space style={{ width: "100%", justifyContent: "flex-end" }}>
          <Button onClick={onClose}>Cancel</Button>
          <Button
            type="primary"
            icon={<SendOutlined />}
            loading={loading}
            onClick={handleSave}
          >
            Save Configuration
          </Button>
        </Space>
      }
    >
      <Form
        form={form}
        layout="vertical"
        initialValues={{
          messageType: "text",
          triggerKeyword: "",
          welcomeMessage: "",
        }}
      >
        <Card title="Message Type" size="small" style={{ marginBottom: 16 }}>
          <Form.Item
            name="messageType"
            rules={[
              { required: true, message: "Please select a message type" },
            ]}
          >
            <Radio.Group>
              <Space direction="vertical" style={{ width: "100%" }}>
                <Radio.Button
                  value="text"
                  style={{ width: "100%", textAlign: "left" }}
                >
                  <Space>
                    <FileTextOutlined />
                    Text Message
                  </Space>
                </Radio.Button>
                {/* <Radio.Button
                  value="media"
                  style={{ width: "100%", textAlign: "left" }}
                >
                  <Space>
                    <PictureOutlined />
                    Media Message
                  </Space>
                </Radio.Button> */}
                <Radio.Button
                  value="template"
                  style={{ width: "100%", textAlign: "left" }}
                >
                  <Space>
                    <FileTextOutlined />
                    Template Message
                  </Space>
                </Radio.Button>
              </Space>
            </Radio.Group>
          </Form.Item>
        </Card>

        <Form.Item
          noStyle
          shouldUpdate={(prevValues, currentValues) =>
            prevValues.messageType !== currentValues.messageType
          }
        >
          {({ getFieldValue }) => {
            const messageType = getFieldValue("messageType");

            if (messageType === "text") {
              return (
                <Card
                  title="Text Message"
                  size="small"
                  style={{ marginBottom: 16 }}
                >
                  <Form.Item
                    name="triggerKeyword"
                    label="Trigger Keyword"
                    rules={[
                      {
                        required: true,
                        message: "Please enter trigger keyword",
                      },
                    ]}
                    help="Enter the keyword that will trigger this welcome message when a customer sends it"
                  >
                    <Input
                      placeholder="e.g., hello, start, help"
                      maxLength={100}
                    />
                  </Form.Item>

                  <Form.Item
                    name="welcomeMessage"
                    label="Welcome Message"
                    rules={[
                      {
                        required: true,
                        message: "Please enter welcome message",
                      },
                    ]}
                    help="This message will be sent when the trigger keyword is matched"
                  >
                    <div>
                      <TextArea
                        rows={4}
                        placeholder="Enter your welcome message..."
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
                </Card>
              );
            }

            if (messageType === "media") {
              return (
                <Card
                  title="Media Message"
                  size="small"
                  style={{ marginBottom: 16 }}
                >
                  <Form.Item
                    name="mediaType"
                    label="Media Type"
                    rules={[
                      {
                        required: true,
                        message: "Please select media type",
                      },
                    ]}
                  >
                    <Select>
                      <Select.Option value="image">
                        <Space>
                          <PictureOutlined />
                          Image
                        </Space>
                      </Select.Option>
                      <Select.Option value="video">
                        <Space>
                          <PictureOutlined />
                          Video
                        </Space>
                      </Select.Option>
                    </Select>
                  </Form.Item>

                  <Form.Item
                    label="Upload Media"
                    rules={[
                      { required: true, message: "Please upload media file" },
                    ]}
                  >
                    <Upload
                      beforeUpload={(file) => {
                        // For now, just show a success message
                        message.success("File uploaded successfully!");
                        // Set the file name in the form
                        form.setFieldsValue({ media: file.name });
                        return false;
                      }}
                      accept=".jpg,.jpeg,.png,.gif,.mp4,.avi,.mov"
                      showUploadList={false}
                    >
                      <Button
                        icon={<UploadOutlined />}
                        style={{ width: "100%" }}
                      >
                        Upload Media
                      </Button>
                    </Upload>
                  </Form.Item>

                  <Form.Item name="media" hidden>
                    <Input />
                  </Form.Item>

                  <Form.Item name="caption" label="Caption (Optional)">
                    <TextArea
                      rows={3}
                      placeholder="Enter caption for the media..."
                      showCount
                      maxLength={500}
                    />
                  </Form.Item>
                </Card>
              );
            }

            if (messageType === "template") {
              return (
                <Card
                  title="Template Message"
                  size="small"
                  style={{ marginBottom: 16 }}
                >
                  <Form.Item
                    name="templateId"
                    label="Select Template"
                    rules={[
                      { required: true, message: "Please select a template" },
                    ]}
                  >
                    <Select
                      placeholder="Choose a template"
                      showSearch
                      optionFilterProp="children"
                      onChange={handleTemplateChange}
                      filterOption={(input, option) => {
                        // Get the template name from the option's data attribute
                        const templateName = option["data-template-name"] || "";
                        return (
                          templateName
                            .toLowerCase()
                            .indexOf(input.toLowerCase()) >= 0
                        );
                      }}
                      style={{
                        height: "74px", // Set the height of the select input
                      }}
                    >
                      {(() => {
                        const filteredTemplates = templates.filter(
                          (template) =>
                            template.is_carousel_template !== 1 &&
                            (template.button_text_flow === null ||
                              template.button_text_flow === "")
                        ); // Exclude carousel templates and WhatsApp Flow templates - they have their own dedicated nodes

                        return filteredTemplates.map((template) => (
                          <Select.Option
                            key={template.id}
                            value={template.id}
                            data-template-name={template.template_name}
                          >
                            <div>
                              <div
                                style={{
                                  display: "flex",
                                  alignItems: "center",
                                  justifyContent: "space-between",
                                  marginBottom: "4px",
                                }}
                              >
                                <div style={{ fontWeight: 500 }}>
                                  {template.template_name}
                                </div>
                                {(template.button_type == 2 ||
                                  template.button_type == 4) && (
                                  <Tag
                                    color="green"
                                    style={{ margin: 0, fontSize: "10px" }}
                                  >
                                    Quick Reply Template
                                  </Tag>
                                )}
                              </div>
                              <div style={{ fontSize: "12px", color: "#666" }}>
                                {template.actual_body?.substring(0, 50)}...
                              </div>
                            </div>
                          </Select.Option>
                        ));
                      })()}
                    </Select>
                  </Form.Item>

                  {/* Quick Reply Info Section */}
                  {renderQuickReplyInfo()}
                </Card>
              );
            }

            return null;
          }}
        </Form.Item>

        {/* Preview Section */}
        <Card title="Preview" size="small">
          <Form.Item
            noStyle
            shouldUpdate={(prevValues, currentValues) =>
              prevValues.messageType !== currentValues.messageType ||
              prevValues.text !== currentValues.text ||
              prevValues.templateId !== currentValues.templateId
            }
          >
            {({ getFieldValue }) => {
              const messageType = getFieldValue("messageType");
              const text = getFieldValue("text");
              const templateId = getFieldValue("templateId");

              const selectedTemplate = templates.find(
                (t) => t.id === templateId
              );

              return (
                <div
                  style={{
                    padding: 12,
                    background: "#f8f9fa",
                    borderRadius: 6,
                  }}
                >
                  <Text type="secondary">Preview:</Text>
                  <div style={{ marginTop: 8 }}>
                    {messageType === "text" && (
                      <div>
                        <Text strong>Text Message:</Text>
                        <Paragraph style={{ margin: "8px 0 0 0" }}>
                          {text || "No text entered"}
                        </Paragraph>
                      </div>
                    )}

                    {messageType === "media" && (
                      <div>
                        <Text strong>Media Message:</Text>
                        <div style={{ marginTop: 8 }}>
                          <Text>Media file will be uploaded</Text>
                        </div>
                      </div>
                    )}

                    {messageType === "template" && (
                      <div>
                        <Text strong>Template Message:</Text>
                        <div style={{ marginTop: 8 }}>
                          <Text>
                            {selectedTemplate?.template_name ||
                              "No template selected"}
                          </Text>
                          {selectedTemplate && (
                            <Paragraph style={{ margin: "8px 0 0 0" }}>
                              {selectedTemplate.actual_body?.substring(0, 100)}
                              ...
                            </Paragraph>
                          )}
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              );
            }}
          </Form.Item>
        </Card>
      </Form>
    </BuilderDrawer>
  );
};

export default ReactFlowWelcomeMessageModule;
