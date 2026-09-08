import React, { useState, useEffect } from "react";
import BuilderDrawer, { BuilderDrawerTitle } from "./BuilderDrawer.jsx";
import {
  Form,
  InputNumber,
  Input,
  Button,
  Space,
  Card,
  Typography,
  message,
  Switch,
  Select,
} from "antd";
import {
  LoadingOutlined,
  SendOutlined,
  ClockCircleOutlined,
  SettingOutlined,
} from "@ant-design/icons";

const { Text } = Typography;

const ReactFlowTypingIndicatorModule = ({
  visible,
  onClose,
  onSave,
  nodeData = null,
}) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  // Initialize form with node data if editing
  useEffect(() => {
    if (visible && nodeData) {
      form.setFieldsValue(nodeData);
    } else if (visible) {
      form.resetFields();
      form.setFieldsValue({
        duration: 3,
        showTyping: true,
        typingText: "Bot is typing...",
        typingSpeed: "normal",
        customMessage: "",
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
        label: `Typing Indicator - ${values.duration}s`,
      };

      onSave(nodeData);
      message.success("Typing indicator configured successfully!");
      onClose();
    } catch (error) {
      console.error("Form validation failed:", error);
    } finally {
      setLoading(false);
    }
  };

  const getTypingSpeedOptions = () => [
    { value: "slow", label: "Slow (2s)" },
    { value: "normal", label: "Normal (3s)" },
    { value: "fast", label: "Fast (1s)" },
    { value: "custom", label: "Custom" },
  ];

  return (
    <BuilderDrawer
      title={
        <BuilderDrawerTitle icon={<LoadingOutlined />}>
          Typing Indicator Configuration
        </BuilderDrawerTitle>
      }
      width={420}
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
          duration: 3,
          showTyping: true,
          typingText: "Bot is typing...",
          typingSpeed: "normal",
          customMessage: "",
        }}
      >
        {/* Basic Configuration */}
        <Card title="Basic Settings" size="small" style={{ marginBottom: 16 }}>
          <Form.Item
            name="showTyping"
            label="Show Typing Indicator"
            valuePropName="checked"
          >
            <Switch />
          </Form.Item>

          <Form.Item
            name="duration"
            label="Duration (seconds)"
            rules={[
              { required: true, message: "Please enter duration" },
              {
                type: "number",
                min: 1,
                max: 60,
                message: "Duration must be between 1-60 seconds",
              },
            ]}
          >
            <InputNumber
              min={1}
              max={60}
              style={{ width: "100%" }}
              addonAfter="seconds"
            />
          </Form.Item>
        </Card>

        {/* Typing Speed Configuration */}
        <Card title="Typing Speed" size="small" style={{ marginBottom: 16 }}>
          <Form.Item
            name="typingSpeed"
            label="Typing Speed"
            rules={[{ required: true, message: "Please select typing speed" }]}
          >
            <Select>
              {getTypingSpeedOptions().map((option) => (
                <Select.Option key={option.value} value={option.value}>
                  <Space>
                    <ClockCircleOutlined />
                    {option.label}
                  </Space>
                </Select.Option>
              ))}
            </Select>
          </Form.Item>

          <Form.Item
            noStyle
            shouldUpdate={(prevValues, currentValues) =>
              prevValues.typingSpeed !== currentValues.typingSpeed
            }
          >
            {({ getFieldValue }) => {
              const typingSpeed = getFieldValue("typingSpeed");

              if (typingSpeed === "custom") {
                return (
                  <Form.Item
                    name="customDuration"
                    label="Custom Duration"
                    rules={[
                      {
                        required: true,
                        message: "Please enter custom duration",
                      },
                      {
                        type: "number",
                        min: 0.5,
                        max: 10,
                        message: "Duration must be between 0.5-10 seconds",
                      },
                    ]}
                  >
                    <InputNumber
                      min={0.5}
                      max={10}
                      step={0.1}
                      style={{ width: "100%" }}
                      addonAfter="seconds"
                    />
                  </Form.Item>
                );
              }
              return null;
            }}
          </Form.Item>
        </Card>

        {/* Typing Text Configuration */}
        <Card title="Typing Text" size="small" style={{ marginBottom: 16 }}>
          <Form.Item
            name="typingText"
            label="Typing Text"
            rules={[{ required: true, message: "Please enter typing text" }]}
          >
            <Input
              placeholder="e.g., Bot is typing..."
              maxLength={100}
              showCount
            />
          </Form.Item>

          <Form.Item name="customMessage" label="Custom Message (Optional)">
            <Input.TextArea
              rows={3}
              placeholder="Enter a custom message to show during typing..."
              maxLength={500}
              showCount
            />
          </Form.Item>
        </Card>

        {/* Advanced Settings */}
        <Card
          title="Advanced Settings"
          size="small"
          style={{ marginBottom: 16 }}
        >
          <Form.Item
            name="showDots"
            label="Show Animated Dots"
            valuePropName="checked"
          >
            <Switch />
          </Form.Item>

          <Form.Item
            name="repeatTyping"
            label="Repeat Typing Indicator"
            valuePropName="checked"
          >
            <Switch />
          </Form.Item>

          <Form.Item
            name="maxRepeats"
            label="Maximum Repeats"
            rules={[
              {
                type: "number",
                min: 1,
                max: 10,
                message: "Repeats must be between 1-10",
              },
            ]}
          >
            <InputNumber
              min={1}
              max={10}
              style={{ width: "100%" }}
              placeholder="Leave empty for unlimited"
            />
          </Form.Item>
        </Card>

        {/* Preview Section */}
        <Card title="Preview" size="small">
          <Form.Item
            noStyle
            shouldUpdate={(prevValues, currentValues) =>
              prevValues.duration !== currentValues.duration ||
              prevValues.typingText !== currentValues.typingText ||
              prevValues.showTyping !== currentValues.showTyping
            }
          >
            {({ getFieldValue }) => {
              const duration = getFieldValue("duration");
              const typingText = getFieldValue("typingText");
              const showTyping = getFieldValue("showTyping");

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
                    <div>
                      <Text strong>Status:</Text>{" "}
                      <Text type={showTyping ? "success" : "secondary"}>
                        {showTyping ? "Enabled" : "Disabled"}
                      </Text>
                    </div>
                    <div>
                      <Text strong>Duration:</Text> {duration} seconds
                    </div>
                    <div>
                      <Text strong>Typing Text:</Text> {typingText}
                    </div>
                    <div style={{ marginTop: 8 }}>
                      <Space>
                        <LoadingOutlined style={{ color: "#1890ff" }} />
                        <Text type="secondary">{typingText}</Text>
                      </Space>
                    </div>
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

export default ReactFlowTypingIndicatorModule;
