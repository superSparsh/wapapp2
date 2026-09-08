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
  BranchesOutlined,
  CheckCircleOutlined,
  CloseCircleOutlined,
  ClockCircleOutlined,
  MessageOutlined,
  PlusOutlined,
  DeleteOutlined,
  EditOutlined,
  CopyOutlined,
  QuestionCircleOutlined,
  ThunderboltOutlined,
  ApiOutlined,
} from "@ant-design/icons";
import VariableHelper from "./VariableHelper.jsx";

const { Text, Paragraph } = Typography;
const { TextArea } = Input;
const { TabPane } = Tabs;
const { Panel } = Collapse;

const ReactFlowEnhancedConditionModule = ({
  visible,
  onClose,
  onSave,
  nodeData = null,
  variables = [],
  templates = [],
  availableSteps = [],
}) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [conditionType, setConditionType] = useState("delivery");
  const [selectedTemplate, setSelectedTemplate] = useState(null);
  const [quickReplies, setQuickReplies] = useState([]);
  const [conditions, setConditions] = useState([]);
  const [showAddConditionModal, setShowAddConditionModal] = useState(false);
  const [editingCondition, setEditingCondition] = useState(null);

  // Initialize form with node data if editing
  useEffect(() => {
    if (visible && nodeData) {
      form.setFieldsValue(nodeData);
      if (nodeData.conditionType) {
        setConditionType(nodeData.conditionType);
      }
      if (nodeData.selectedTemplate) {
        setSelectedTemplate(nodeData.selectedTemplate);
      }
      if (nodeData.quickReplies) {
        setQuickReplies(nodeData.quickReplies);
      }
      if (nodeData.conditions) {
        setConditions(nodeData.conditions);
      }
    } else if (visible) {
      form.resetFields();
      setConditionType("delivery");
      setSelectedTemplate(null);
      setQuickReplies([]);
      setConditions([]);
      form.setFieldsValue({
        conditionType: "delivery",
        templateId: "",
        deliveryTimeout: 30,
        retryCount: 3,
        retryDelay: 60,
        conditions: [],
        quickReplies: [],
        defaultBranch: "continue",
        errorBranch: "fallback",
        timeoutBranch: "timeout",
      });
    }
  }, [visible, nodeData, form]);

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      // Process the form data
      const nodeData = {
        ...values,
        conditionType: conditionType,
        selectedTemplate: selectedTemplate,
        quickReplies: quickReplies,
        conditions: conditions,
        label: `Enhanced Condition - ${getConditionTypeLabel(conditionType)}`,
      };

      onSave(nodeData);
      message.success("Enhanced condition configuration saved successfully!");
      onClose();
    } catch (error) {
      console.error("Form validation failed:", error);
    } finally {
      setLoading(false);
    }
  };

  const handleVariableSelect = (variableSyntax, fieldName) => {
    const currentValue = form.getFieldValue(fieldName) || "";
    form.setFieldsValue({ [fieldName]: currentValue + variableSyntax });
  };

  const getConditionTypeLabel = (type) => {
    const labels = {
      delivery: "Delivery Status",
      user_response: "User Response",
      custom_condition: "Custom Condition",
      time_based: "Time Based",
      api_response: "API Response",
    };
    return labels[type] || "Enhanced Condition";
  };

  const getConditionTypeIcon = (type) => {
    const icons = {
      delivery: <CheckCircleOutlined />,
      user_response: <MessageOutlined />,
      custom_condition: <BranchesOutlined />,
      time_based: <ClockCircleOutlined />,
      api_response: <ApiOutlined />,
    };
    return icons[type] || <BranchesOutlined />;
  };

  const handleTemplateChange = (templateId) => {
    const template = templates.find((t) => t.id === templateId);
    if (template) {
      setSelectedTemplate(template);
      // Extract quick replies from template
      if (template.quick_replies && template.quick_replies.length > 0) {
        setQuickReplies(
          template.quick_replies.map((reply, index) => ({
            id: `reply_${index}`,
            text: reply.text,
            action: reply.action || "reply",
            value: reply.value || reply.text,
          }))
        );
      } else {
        setQuickReplies([]);
      }
    }
  };

  const handleAddCondition = (conditionData) => {
    if (editingCondition !== null) {
      const updatedConditions = conditions.map((condition, index) =>
        index === editingCondition
          ? { ...conditionData, id: condition.id }
          : condition
      );
      setConditions(updatedConditions);
      setEditingCondition(null);
    } else {
      const newCondition = {
        ...conditionData,
        id: `condition_${Date.now()}`,
      };
      setConditions([...conditions, newCondition]);
    }
    setShowAddConditionModal(false);
    form.resetFields([
      "conditionName",
      "conditionType",
      "conditionValue",
      "conditionOperator",
    ]);
  };

  const handleEditCondition = (index) => {
    const condition = conditions[index];
    setEditingCondition(index);
    form.setFieldsValue({
      conditionName: condition.name,
      conditionType: condition.type,
      conditionValue: condition.value,
      conditionOperator: condition.operator,
    });
    setShowAddConditionModal(true);
  };

  const handleDeleteCondition = (index) => {
    const updatedConditions = conditions.filter((_, i) => i !== index);
    setConditions(updatedConditions);
  };

  const renderDeliveryStatusForm = () => (
    <Card
      title="Delivery Status Configuration"
      size="small"
      style={{ marginBottom: 16 }}
    >
      <Form.Item
        name="deliveryTimeout"
        label="Delivery Timeout (seconds)"
        rules={[{ required: true, message: "Please enter delivery timeout" }]}
      >
        <Input type="number" min={1} max={300} placeholder="30" />
      </Form.Item>

      <Form.Item
        name="retryCount"
        label="Retry Count"
        rules={[{ required: true, message: "Please enter retry count" }]}
      >
        <Input type="number" min={0} max={10} placeholder="3" />
      </Form.Item>

      <Form.Item
        name="retryDelay"
        label="Retry Delay (seconds)"
        rules={[{ required: true, message: "Please enter retry delay" }]}
      >
        <Input type="number" min={1} max={3600} placeholder="60" />
      </Form.Item>

      <Form.Item label="Branching Configuration">
        <div style={{ marginBottom: 16 }}>
          <Text type="secondary" style={{ fontSize: "12px", marginBottom: 8 }}>
            Configure where to route based on delivery status
          </Text>
        </div>

        <Row gutter={16}>
          <Col span={8}>
            <Form.Item name="deliveredBranch" label="Delivered">
              <Select placeholder="Select target step">
                {availableSteps.map((step) => (
                  <Select.Option key={step.id} value={step.id}>
                    {step.name}
                  </Select.Option>
                ))}
              </Select>
            </Form.Item>
          </Col>
          <Col span={8}>
            <Form.Item name="undeliveredBranch" label="Undelivered">
              <Select placeholder="Select target step">
                {availableSteps.map((step) => (
                  <Select.Option key={step.id} value={step.id}>
                    {step.name}
                  </Select.Option>
                ))}
              </Select>
            </Form.Item>
          </Col>
          <Col span={8}>
            <Form.Item name="failedBranch" label="Failed">
              <Select placeholder="Select target step">
                {availableSteps.map((step) => (
                  <Select.Option key={step.id} value={step.id}>
                    {step.name}
                  </Select.Option>
                ))}
              </Select>
            </Form.Item>
          </Col>
        </Row>
      </Form.Item>
    </Card>
  );

  const renderTemplateRepliesForm = () => (
    <Card
      title="Template Quick Replies Configuration"
      size="small"
      style={{ marginBottom: 16 }}
    >
      <Form.Item
        name="templateId"
        label="Select Template"
        rules={[{ required: true, message: "Please select a template" }]}
      >
        <Select
          placeholder="Choose a template with quick replies"
          onChange={handleTemplateChange}
          showSearch
          optionFilterProp="children"
          filterOption={(input, option) =>
            option.children.toLowerCase().indexOf(input.toLowerCase()) >= 0
          }
        >
          {templates
            .filter(
              (template) =>
                template.quick_replies && template.quick_replies.length > 0
            )
            .map((template) => (
              <Select.Option key={template.id} value={template.id}>
                <div>
                  <div style={{ fontWeight: 500 }}>
                    {template.template_name}
                  </div>
                  <div style={{ fontSize: "12px", color: "#666" }}>
                    {template.quick_replies.length} quick replies
                  </div>
                </div>
              </Select.Option>
            ))}
        </Select>
      </Form.Item>

      {selectedTemplate && (
        <Form.Item label="Quick Replies Preview">
          <div style={{ marginBottom: 16 }}>
            <Text
              type="secondary"
              style={{ fontSize: "12px", marginBottom: 8 }}
            >
              Quick replies from selected template - each will create a separate
              branch
            </Text>
          </div>

          {quickReplies.map((reply, index) => (
            <Card
              key={reply.id}
              size="small"
              style={{ marginBottom: 8 }}
              title={
                <Space>
                  <Text strong>{reply.text}</Text>
                  <Tag color="blue">{reply.action}</Tag>
                </Space>
              }
              extra={
                <Form.Item
                  name={`replyBranch_${reply.id}`}
                  style={{ margin: 0 }}
                >
                  <Select
                    placeholder="Select target step"
                    style={{ width: 200 }}
                    size="small"
                  >
                    {availableSteps.map((step) => (
                      <Select.Option key={step.id} value={step.id}>
                        {step.name}
                      </Select.Option>
                    ))}
                  </Select>
                </Form.Item>
              }
            >
              <div style={{ fontSize: "12px", color: "#666" }}>
                Action: {reply.action} | Value: {reply.value}
              </div>
            </Card>
          ))}
        </Form.Item>
      )}

      <Form.Item name="defaultReplyBranch" label="Default Branch (No Reply)">
        <Select placeholder="Select target step for no reply">
          {availableSteps.map((step) => (
            <Select.Option key={step.id} value={step.id}>
              {step.name}
            </Select.Option>
          ))}
        </Select>
      </Form.Item>
    </Card>
  );

  const renderUserResponseForm = () => (
    <Card
      title="User Response Configuration"
      size="small"
      style={{ marginBottom: 16 }}
    >
      <Form.Item
        name="responseTimeout"
        label="Response Timeout (seconds)"
        rules={[{ required: true, message: "Please enter response timeout" }]}
      >
        <Input type="number" min={1} max={3600} placeholder="300" />
      </Form.Item>

      <Form.Item label="Response Conditions">
        <div style={{ marginBottom: 16 }}>
          <Text type="secondary" style={{ fontSize: "12px", marginBottom: 8 }}>
            Add conditions to route based on user response content
          </Text>
          <Button
            type="dashed"
            block
            icon={<PlusOutlined />}
            onClick={() => setShowAddConditionModal(true)}
          >
            Add Condition
          </Button>
        </div>

        {conditions.map((condition, index) => (
          <Card
            key={condition.id}
            size="small"
            style={{ marginBottom: 8 }}
            title={
              <Space>
                <Text strong>{condition.name}</Text>
                <Tag
                  color={
                    condition.type === "exact"
                      ? "blue"
                      : condition.type === "contains"
                      ? "green"
                      : "orange"
                  }
                >
                  {condition.operator}
                </Tag>
              </Space>
            }
            extra={
              <Space>
                <Button
                  type="text"
                  size="small"
                  icon={<EditOutlined />}
                  onClick={() => handleEditCondition(index)}
                />
                <Button
                  type="text"
                  size="small"
                  danger
                  icon={<DeleteOutlined />}
                  onClick={() => handleDeleteCondition(index)}
                />
              </Space>
            }
          >
            <div style={{ fontSize: "12px", color: "#666" }}>
              {condition.operator} "{condition.value}"
            </div>
            <Form.Item
              name={`conditionBranch_${condition.id}`}
              style={{ marginTop: 8, marginBottom: 0 }}
            >
              <Select placeholder="Select target step" size="small">
                {availableSteps.map((step) => (
                  <Select.Option key={step.id} value={step.id}>
                    {step.name}
                  </Select.Option>
                ))}
              </Select>
            </Form.Item>
          </Card>
        ))}
      </Form.Item>

      <Form.Item name="defaultResponseBranch" label="Default Branch (No Match)">
        <Select placeholder="Select target step for no match">
          {availableSteps.map((step) => (
            <Select.Option key={step.id} value={step.id}>
              {step.name}
            </Select.Option>
          ))}
        </Select>
      </Form.Item>
    </Card>
  );

  const renderCustomConditionForm = () => (
    <Card
      title="Custom Condition Configuration"
      size="small"
      style={{ marginBottom: 16 }}
    >
      <Form.Item
        name="customCondition"
        label="Custom Condition Logic"
        rules={[
          { required: true, message: "Please enter custom condition logic" },
        ]}
      >
        <div>
          <TextArea
            rows={6}
            placeholder="Enter JavaScript condition logic. Use 'response' for user response, 'context' for flow context..."
            maxLength={1000}
            showCount
          />
          <div style={{ marginTop: 8 }}>
            <VariableHelper
              variables={variables}
              onVariableSelect={(syntax) =>
                handleVariableSelect(syntax, "customCondition")
              }
              placeholder="Insert Variable"
              size="small"
            />
          </div>
        </div>
      </Form.Item>

      <Form.Item name="trueBranch" label="True Branch">
        <Select placeholder="Select target step when condition is true">
          {availableSteps.map((step) => (
            <Select.Option key={step.id} value={step.id}>
              {step.name}
            </Select.Option>
          ))}
        </Select>
      </Form.Item>

      <Form.Item name="falseBranch" label="False Branch">
        <Select placeholder="Select target step when condition is false">
          {availableSteps.map((step) => (
            <Select.Option key={step.id} value={step.id}>
              {step.name}
            </Select.Option>
          ))}
        </Select>
      </Form.Item>
    </Card>
  );

  const renderTimeBasedForm = () => (
    <Card
      title="Time Based Configuration"
      size="small"
      style={{ marginBottom: 16 }}
    >
      <Form.Item
        name="timeCondition"
        label="Time Condition"
        rules={[{ required: true, message: "Please select time condition" }]}
      >
        <Select placeholder="Select time condition">
          <Select.Option value="business_hours">Business Hours</Select.Option>
          <Select.Option value="weekend">Weekend</Select.Option>
          <Select.Option value="specific_time">
            Specific Time Range
          </Select.Option>
          <Select.Option value="timezone">Based on Timezone</Select.Option>
        </Select>
      </Form.Item>

      <Form.Item name="timezone" label="Timezone">
        <Select placeholder="Select timezone" defaultValue="UTC">
          <Select.Option value="UTC">UTC</Select.Option>
          <Select.Option value="America/New_York">Eastern Time</Select.Option>
          <Select.Option value="America/Chicago">Central Time</Select.Option>
          <Select.Option value="America/Denver">Mountain Time</Select.Option>
          <Select.Option value="America/Los_Angeles">
            Pacific Time
          </Select.Option>
          <Select.Option value="Europe/London">London</Select.Option>
          <Select.Option value="Europe/Paris">Paris</Select.Option>
          <Select.Option value="Asia/Tokyo">Tokyo</Select.Option>
          <Select.Option value="Asia/Shanghai">Shanghai</Select.Option>
          <Select.Option value="Asia/Kolkata">India</Select.Option>
        </Select>
      </Form.Item>

      <Form.Item name="timeTrueBranch" label="True Branch (Condition Met)">
        <Select placeholder="Select target step when time condition is met">
          {availableSteps.map((step) => (
            <Select.Option key={step.id} value={step.id}>
              {step.name}
            </Select.Option>
          ))}
        </Select>
      </Form.Item>

      <Form.Item
        name="timeFalseBranch"
        label="False Branch (Condition Not Met)"
      >
        <Select placeholder="Select target step when time condition is not met">
          {availableSteps.map((step) => (
            <Select.Option key={step.id} value={step.id}>
              {step.name}
            </Select.Option>
          ))}
        </Select>
      </Form.Item>
    </Card>
  );

  const renderApiResponseForm = () => (
    <Card
      title="API Response Configuration"
      size="small"
      style={{ marginBottom: 16 }}
    >
      <Form.Item
        name="apiEndpoint"
        label="API Endpoint"
        rules={[{ required: true, message: "Please enter API endpoint" }]}
      >
        <Input placeholder="https://api.example.com/condition" />
      </Form.Item>

      <Form.Item
        name="apiMethod"
        label="HTTP Method"
        rules={[{ required: true, message: "Please select HTTP method" }]}
      >
        <Select placeholder="Select HTTP method">
          <Select.Option value="GET">GET</Select.Option>
          <Select.Option value="POST">POST</Select.Option>
          <Select.Option value="PUT">PUT</Select.Option>
          <Select.Option value="PATCH">PATCH</Select.Option>
        </Select>
      </Form.Item>

      <Form.Item
        name="apiTimeout"
        label="API Timeout (seconds)"
        rules={[{ required: true, message: "Please enter API timeout" }]}
      >
        <Input type="number" min={1} max={60} placeholder="10" />
      </Form.Item>

      <Form.Item
        name="apiCondition"
        label="Response Condition"
        rules={[{ required: true, message: "Please enter response condition" }]}
      >
        <TextArea
          rows={4}
          placeholder="Enter condition to evaluate API response. Use 'response' for API response data..."
          maxLength={500}
          showCount
        />
      </Form.Item>

      <Form.Item name="apiTrueBranch" label="True Branch (Condition Met)">
        <Select placeholder="Select target step when API condition is met">
          {availableSteps.map((step) => (
            <Select.Option key={step.id} value={step.id}>
              {step.name}
            </Select.Option>
          ))}
        </Select>
      </Form.Item>

      <Form.Item name="apiFalseBranch" label="False Branch (Condition Not Met)">
        <Select placeholder="Select target step when API condition is not met">
          {availableSteps.map((step) => (
            <Select.Option key={step.id} value={step.id}>
              {step.name}
            </Select.Option>
          ))}
        </Select>
      </Form.Item>

      <Form.Item name="apiErrorBranch" label="Error Branch (API Failed)">
        <Select placeholder="Select target step when API fails">
          {availableSteps.map((step) => (
            <Select.Option key={step.id} value={step.id}>
              {step.name}
            </Select.Option>
          ))}
        </Select>
      </Form.Item>
    </Card>
  );

  const renderFormByType = () => {
    switch (conditionType) {
      case "delivery":
        return renderDeliveryStatusForm();

      case "user_response":
        return renderUserResponseForm();
      case "custom_condition":
        return renderCustomConditionForm();
      case "time_based":
        return renderTimeBasedForm();
      case "api_response":
        return renderApiResponseForm();
      default:
        return renderDeliveryStatusForm();
    }
  };

  return (
    <>
      <BuilderDrawer
        title={
          <div className="chatbot-builder-drawer__title">
            <span className="chatbot-builder-drawer__title-icon">
              {getConditionTypeIcon(conditionType)}
            </span>
            <span className="chatbot-builder-drawer__title-text">
              Enhanced Condition Configuration
            </span>
            <Tag color="green">{getConditionTypeLabel(conditionType)}</Tag>
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
              icon={<ThunderboltOutlined />}
              loading={loading}
              onClick={handleSave}
            >
              Save Configuration
            </Button>
          </Space>
        }
      >
        <Form form={form} layout="vertical">
          {/* Condition Type Selection */}
          <Card
            title="Condition Type"
            size="small"
            style={{ marginBottom: 16 }}
          >
            <Form.Item name="conditionType" initialValue="delivery">
              <Select
                placeholder="Select condition type"
                onChange={(value) => setConditionType(value)}
                value={conditionType}
              >
                <Select.Option value="delivery">
                  <Space>
                    <CheckCircleOutlined />
                    Delivery Status
                  </Space>
                </Select.Option>

                <Select.Option value="user_response">
                  <Space>
                    <MessageOutlined />
                    User Response
                  </Space>
                </Select.Option>
                <Select.Option value="custom_condition">
                  <Space>
                    <BranchesOutlined />
                    Custom Condition
                  </Space>
                </Select.Option>
                <Select.Option value="time_based">
                  <Space>
                    <ClockCircleOutlined />
                    Time Based
                  </Space>
                </Select.Option>
                <Select.Option value="api_response">
                  <Space>
                    <ApiOutlined />
                    API Response
                  </Space>
                </Select.Option>
              </Select>
            </Form.Item>
          </Card>

          {/* Dynamic Form Based on Type */}
          {renderFormByType()}

          {/* Branching Guidelines */}
          <Card title="Branching Guidelines" size="small">
            <div style={{ fontSize: "12px", color: "#666" }}>
              <Paragraph style={{ marginBottom: 8 }}>
                <Text strong>Enhanced Condition Branching:</Text>
              </Paragraph>
              <ul style={{ margin: 0, paddingLeft: 16 }}>
                <li>
                  <strong>Delivery Status:</strong> Route based on message
                  delivery success/failure
                </li>

                <li>
                  <strong>User Response:</strong> Route based on user's text
                  response content
                </li>
                <li>
                  <strong>Custom Condition:</strong> Use JavaScript logic for
                  complex branching
                </li>
                <li>
                  <strong>Time Based:</strong> Route based on time, day, or
                  business hours
                </li>
                <li>
                  <strong>API Response:</strong> Route based on external API
                  response data
                </li>
              </ul>

              <Divider style={{ margin: "12px 0" }} />

              <Paragraph style={{ marginBottom: 8 }}>
                <Text strong>Best Practices:</Text>
              </Paragraph>
              <ul style={{ margin: 0, paddingLeft: 16 }}>
                <li>
                  Always provide a default branch for unmatched conditions
                </li>
                <li>Use clear, descriptive condition names</li>
                <li>Test branching logic thoroughly before deployment</li>
                <li>Consider error handling and fallback scenarios</li>
                <li>Keep custom conditions simple and readable</li>
              </ul>
            </div>
          </Card>
        </Form>

        {/* Add Condition Modal */}
        <Modal
          title={editingCondition !== null ? "Edit Condition" : "Add Condition"}
          open={showAddConditionModal}
          onOk={() => {
            const values = form.getFieldsValue([
              "conditionName",
              "conditionType",
              "conditionValue",
              "conditionOperator",
            ]);
            if (
              values.conditionName &&
              values.conditionType &&
              values.conditionValue
            ) {
              handleAddCondition({
                name: values.conditionName,
                type: values.conditionType,
                value: values.conditionValue,
                operator: values.conditionOperator,
              });
            } else {
              message.error("Please fill all required fields");
            }
          }}
          onCancel={() => {
            setShowAddConditionModal(false);
            setEditingCondition(null);
            form.resetFields([
              "conditionName",
              "conditionType",
              "conditionValue",
              "conditionOperator",
            ]);
          }}
        >
          <Form.Item name="conditionName" label="Condition Name" required>
            <Input placeholder="Enter condition name" />
          </Form.Item>
          <Form.Item name="conditionType" label="Condition Type" required>
            <Select placeholder="Select condition type">
              <Select.Option value="exact">Exact Match</Select.Option>
              <Select.Option value="contains">Contains</Select.Option>
              <Select.Option value="starts_with">Starts With</Select.Option>
              <Select.Option value="ends_with">Ends With</Select.Option>
              <Select.Option value="regex">Regular Expression</Select.Option>
            </Select>
          </Form.Item>
          <Form.Item name="conditionValue" label="Condition Value" required>
            <Input placeholder="Enter condition value" />
          </Form.Item>
          <Form.Item name="conditionOperator" label="Operator" required>
            <Select placeholder="Select operator">
              <Select.Option value="equals">Equals</Select.Option>
              <Select.Option value="not_equals">Not Equals</Select.Option>
              <Select.Option value="greater_than">Greater Than</Select.Option>
              <Select.Option value="less_than">Less Than</Select.Option>
            </Select>
          </Form.Item>
        </Modal>
      </BuilderDrawer>
    </>
  );
};

export default ReactFlowEnhancedConditionModule;
