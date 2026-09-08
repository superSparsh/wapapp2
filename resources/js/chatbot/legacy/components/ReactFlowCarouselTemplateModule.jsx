import React, { useState, useEffect } from "react";
import BuilderDrawer, { BuilderDrawerTitle } from "./BuilderDrawer.jsx";
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
  CarOutlined,
  LinkOutlined,
  PhoneOutlined,
} from "@ant-design/icons";
import VariableHelper from "./VariableHelper.jsx";
import axios from "axios";

const { Text, Paragraph } = Typography;
const { TextArea } = Input;
const { TabPane } = Tabs;
const { Panel } = Collapse;

const ReactFlowCarouselTemplateModule = ({
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
  const [templateCards, setTemplateCards] = useState([]);
  const [loadingCards, setLoadingCards] = useState(false);

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
          fetchTemplateCards(template.id);
        }
      }
      if (nodeData.timeoutConfig) {
        setTimeoutConfig(nodeData.timeoutConfig);
      }
    } else if (visible) {
      // Reset all form fields and state for new node
      form.resetFields();
      setSelectedTemplate(null);
      setTemplateCards([]);
      setTimeoutConfig({
        unreadTimeout: 300,
        undeliveredTimeout: 60,
      });
      form.setFieldsValue({
        templateId: "",
        timeoutConfig: {
          deliveredTimeout: 0,
          unreadTimeout: 300,
          undeliveredTimeout: 60,
        },
      });
    }
  }, [visible, nodeData, form, templates]);

  const fetchTemplateCards = async (templateId) => {
    try {
      setLoadingCards(true);
      const response = await axios.get(`/template-cards/${templateId}`);
      console.log("Carousel cards response:", response);
      const cardsWithButtons = response.data.cards.map((card) => ({
        ...card,
        buttons: card.buttons || [],
      }));
      setTemplateCards(cardsWithButtons);
      console.log("Template cards:", cardsWithButtons);
    } catch (error) {
      console.error("Error fetching template cards:", error);
      message.error("Failed to fetch carousel cards");
    } finally {
      setLoadingCards(false);
    }
  };

  const handleTemplateChange = (templateId) => {
    const template = templates.find((t) => t.id === templateId);
    if (template) {
      setSelectedTemplate(template);
      fetchTemplateCards(template.id);
      form.setFieldsValue({
        templateId: templateId,
      });
    }
  };

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      console.log("handleSave - form values:", values);
      console.log("handleSave - selectedTemplate:", selectedTemplate);
      console.log("handleSave - templateCards:", templateCards);

      // Process the form data
      const nodeData = {
        ...values,
        selectedTemplate: selectedTemplate,
        templateCards: templateCards,
        timeoutConfig: {
          unreadTimeout: timeoutConfig.unreadTimeout,
          undeliveredTimeout: timeoutConfig.undeliveredTimeout,
        },
        // Ensure templateId is set if we have a selected template
        templateId: selectedTemplate ? selectedTemplate.id : values.templateId,
        label: `Carousel Template - ${
          selectedTemplate?.template_name || "Carousel"
        }`,
      };

      console.log("handleSave - final nodeData:", nodeData);
      onSave(nodeData);
      message.success("Carousel template configuration saved!");
      onClose();
    } catch (error) {
      console.error("Error saving carousel template:", error);
      message.error("Failed to save carousel template configuration");
    } finally {
      setLoading(false);
    }
  };

  const handleVariableSelect = (variableSyntax, fieldName) => {
    const currentValue = form.getFieldValue(fieldName) || "";
    form.setFieldsValue({
      [fieldName]: currentValue + variableSyntax,
    });
  };

  const getButtonTypeIcon = (type) => {
    switch (type) {
      case "PHONE_NUMBER":
        return <PhoneOutlined style={{ color: "#52c41a" }} />;
      case "URL":
        return <LinkOutlined style={{ color: "#1890ff" }} />;
      case "QUICK_REPLY":
        return <MessageOutlined style={{ color: "#fa8c16" }} />;
      default:
        return <MessageOutlined />;
    }
  };

  const getButtonTypeColor = (type) => {
    switch (type) {
      case "PHONE_NUMBER":
        return "green";
      case "URL":
        return "blue";
      case "QUICK_REPLY":
        return "orange";
      default:
        return "default";
    }
  };

  const renderCarouselPreview = () => {
    if (!selectedTemplate || templateCards.length === 0) {
      return (
        <div style={{ textAlign: "center", padding: "20px", color: "#999" }}>
          <CarOutlined style={{ fontSize: "48px", marginBottom: "16px" }} />
          <div>No carousel template selected</div>
        </div>
      );
    }

    return (
      <div>
        <div style={{ marginBottom: 16 }}>
          <Text strong>Carousel Preview:</Text>
        </div>
        <div
          style={{
            display: "flex",
            overflowX: "auto",
            gap: "12px",
            padding: "8px",
            border: "1px solid #f0f0f0",
            borderRadius: "8px",
            backgroundColor: "#fafafa",
          }}
        >
          {templateCards.map((card, cardIndex) => (
            <Card
              key={cardIndex}
              size="small"
              style={{
                minWidth: "200px",
                maxWidth: "200px",
                flex: "0 0 auto",
              }}
              title={`Card ${cardIndex + 1}`}
            >
              <div style={{ fontSize: "12px" }}>
                {card.header_media && (
                  <div style={{ marginBottom: 8 }}>
                    <Text type="secondary">Media: {card.header_media}</Text>
                  </div>
                )}
                {card.body_text && (
                  <div style={{ marginBottom: 8 }}>
                    <Text>{card.body_text.substring(0, 50)}...</Text>
                  </div>
                )}
                {card.buttons && card.buttons.length > 0 && (
                  <div>
                    <Text type="secondary">Buttons:</Text>
                    {card.buttons.map((button, buttonIndex) => (
                      <div
                        key={buttonIndex}
                        style={{
                          display: "flex",
                          alignItems: "center",
                          gap: "4px",
                          marginTop: "4px",
                        }}
                      >
                        {getButtonTypeIcon(button.type)}
                        <Text style={{ fontSize: "11px" }}>{button.text}</Text>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </Card>
          ))}
        </div>
      </div>
    );
  };

  const renderCarouselOptions = () => {
    if (!selectedTemplate || templateCards.length === 0) {
      return null;
    }

    return (
      <Card
        title="Carousel Button Options"
        size="small"
        style={{ marginBottom: 16 }}
      >
        <div style={{ fontSize: "12px", color: "#666", marginBottom: 16 }}>
          Each card can have up to 2 buttons. Only <strong>Quick Reply</strong>{" "}
          buttons create branching connections.
        </div>

        <List
          size="small"
          dataSource={templateCards}
          renderItem={(card, cardIndex) => (
            <List.Item>
              <div style={{ width: "100%" }}>
                <div style={{ fontWeight: "500", marginBottom: 8 }}>
                  Card {cardIndex + 1}
                </div>
                {card.buttons && card.buttons.length > 0 ? (
                  <div
                    style={{
                      display: "flex",
                      flexDirection: "column",
                      gap: "4px",
                    }}
                  >
                    {card.buttons.map((button, buttonIndex) => (
                      <div
                        key={buttonIndex}
                        style={{
                          display: "flex",
                          alignItems: "center",
                          gap: "8px",
                          padding: "4px 8px",
                          backgroundColor: "#f5f5f5",
                          borderRadius: "4px",
                        }}
                      >
                        {getButtonTypeIcon(button.type)}
                        <Text style={{ fontSize: "12px" }}>{button.text}</Text>
                        <Tag
                          color={getButtonTypeColor(button.type)}
                          size="small"
                        >
                          {button.type}
                        </Tag>
                      </div>
                    ))}
                  </div>
                ) : (
                  <Text type="secondary" style={{ fontSize: "12px" }}>
                    No buttons configured
                  </Text>
                )}
              </div>
            </List.Item>
          )}
        />
      </Card>
    );
  };

  return (
    <BuilderDrawer
      title={
        <BuilderDrawerTitle icon={<PictureOutlined />}>
          Carousel Template Configuration
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
      <Form form={form} layout="vertical">
        {/* Template Selection */}
        <Card
          title="Template Selection"
          size="small"
          style={{ marginBottom: 16 }}
        >
          <Form.Item
            label="Select Carousel Template"
            name="templateId"
            rules={[
              { required: true, message: "Please select a carousel template" },
            ]}
          >
            <Select
              placeholder="Select a carousel template"
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
                .filter((template) => template.is_carousel_template === 1) // Only show carousel templates
                .map((template) => (
                  <Select.Option
                    key={template.id}
                    value={template.id}
                    data-template-name={template.template_name}
                  >
                    <div
                      style={{
                        display: "flex",
                        alignItems: "center",
                        gap: "8px",
                      }}
                    >
                      <CarOutlined style={{ color: "#1890ff" }} />
                      <span>{template.template_name}</span>
                      <Tag color="blue" size="small">
                        Carousel
                      </Tag>
                    </div>
                  </Select.Option>
                ))}
            </Select>
          </Form.Item>
        </Card>

        {/* Carousel Preview */}
        {selectedTemplate && (
          <Card
            title="Carousel Preview"
            size="small"
            style={{ marginBottom: 16 }}
          >
            {loadingCards ? (
              <div style={{ textAlign: "center", padding: "20px" }}>
                <Spin />
                <div style={{ marginTop: 8 }}>Loading carousel cards...</div>
              </div>
            ) : (
              renderCarouselPreview()
            )}
          </Card>
        )}

        {/* Carousel Options */}
        {selectedTemplate && renderCarouselOptions()}

        {/* Timeout Configuration */}

        {/* Usage Guidelines */}
        <Card title="Carousel Template Guidelines" size="small">
          <div style={{ fontSize: "12px", color: "#666" }}>
            <Paragraph style={{ marginBottom: 8 }}>
              <Text strong>WhatsApp Carousel Templates:</Text>
            </Paragraph>
            <ul style={{ margin: 0, paddingLeft: 16 }}>
              <li>Carousel templates can contain up to 10 cards</li>
              <li>Each card can have up to 2 interactive buttons</li>
              <li>Button types: URL, Phone Number, or Quick Reply</li>
              <li>Cards are displayed in a horizontal scrollable format</li>
              <li>
                <strong>Only Quick Reply buttons</strong> create branching
                connections
              </li>
              <li>
                URL and Phone Number buttons execute immediately (no branching)
              </li>
              <li>
                Always provide delivery status branches for error handling
              </li>
            </ul>

            <Divider style={{ margin: "12px 0" }} />

            <Paragraph style={{ marginBottom: 8 }}>
              <Text strong>Canvas-Based Carousel Branching:</Text>
            </Paragraph>
            <ul style={{ margin: 0, paddingLeft: 16 }}>
              <li>
                <strong>Quick Reply buttons</strong> appear as blue handles
                below the carousel node
              </li>
              <li>
                Drag from these handles to connect to target nodes on the canvas
              </li>
              <li>
                Each Quick Reply connection is labeled with the button text
              </li>
              <li>
                URL and Phone Number buttons don't have handles (execute
                immediately)
              </li>
              <li>
                Delivery status handles (green/orange/red) handle message
                delivery states
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

export default ReactFlowCarouselTemplateModule;
