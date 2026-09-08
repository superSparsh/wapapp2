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
  TimePicker,
  DatePicker,
  Tag,
} from "antd";
import {
  ClockCircleOutlined,
  SendOutlined,
  CalendarOutlined,
  SettingOutlined,
} from "@ant-design/icons";
import dayjs from "dayjs";

const { Text } = Typography;

const ReactFlowDelayModule = ({
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
        delayType: "fixed",
        delayInSeconds: 5,
        delayInMinutes: 1,
        delayInHours: 0,
        delayUntilTime: null,
        delayUntilDate: null,
        showProgress: true,
        progressMessage: "Please wait...",
        completionMessage: "Delay completed! Proceeding...",
        skipCondition: false,
        skipConditionType: "user_input",
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
        label: `Delay - ${getDelayLabel(values)}`,
      };

      onSave(nodeData);
      message.success("Delay configured successfully!");
      onClose();
    } catch (error) {
      console.error("Form validation failed:", error);
    } finally {
      setLoading(false);
    }
  };

  const getDelayLabel = (values) => {
    const { delayType, delayInSeconds, delayInMinutes, delayInHours } = values;

    if (delayType === "fixed") {
      const totalSeconds =
        delayInHours * 3600 + delayInMinutes * 60 + delayInSeconds;
      if (totalSeconds >= 3600) {
        return `${Math.floor(totalSeconds / 3600)}h ${Math.floor(
          (totalSeconds % 3600) / 60
        )}m`;
      } else if (totalSeconds >= 60) {
        return `${Math.floor(totalSeconds / 60)}m ${totalSeconds % 60}s`;
      } else {
        return `${totalSeconds}s`;
      }
    } else if (delayType === "until_time") {
      return "Until specific time";
    } else if (delayType === "until_date") {
      return "Until specific date";
    }
    return "Custom delay";
  };

  const getDelayTypeOptions = () => [
    { value: "fixed", label: "Fixed Duration" },
    { value: "until_time", label: "Until Specific Time" },
    { value: "until_date", label: "Until Specific Date" },
    { value: "random", label: "Random Duration" },
  ];

  const getSkipConditionOptions = () => [
    { value: "user_input", label: "User Input" },
    { value: "button_click", label: "Button Click" },
    { value: "timeout", label: "Timeout" },
    { value: "never", label: "Never Skip" },
  ];

  return (
    <BuilderDrawer
      title={
        <BuilderDrawerTitle icon={<ClockCircleOutlined />}>
          Delay Configuration
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
          delayType: "fixed",
          delayInSeconds: 5,
          delayInMinutes: 1,
          delayInHours: 0,
          delayUntilTime: null,
          delayUntilDate: null,
          showProgress: true,
          progressMessage: "Please wait...",
          skipCondition: false,
          skipConditionType: "user_input",
        }}
      >
        {/* Delay Type Selection */}
        <Card title="Delay Type" size="small" style={{ marginBottom: 16 }}>
          <Form.Item
            name="delayType"
            label="Type of Delay"
            rules={[{ required: true, message: "Please select delay type" }]}
          >
            <Select>
              {getDelayTypeOptions().map((option) => (
                <Select.Option key={option.value} value={option.value}>
                  <Space>
                    <ClockCircleOutlined />
                    {option.label}
                  </Space>
                </Select.Option>
              ))}
            </Select>
          </Form.Item>
        </Card>

        {/* Conditional Delay Configuration */}
        <Form.Item
          noStyle
          shouldUpdate={(prevValues, currentValues) =>
            prevValues.delayType !== currentValues.delayType
          }
        >
          {({ getFieldValue }) => {
            const delayType = getFieldValue("delayType");

            if (delayType === "fixed") {
              return (
                <Card
                  title="Fixed Duration"
                  size="small"
                  style={{ marginBottom: 16 }}
                >
                  <Space direction="vertical" style={{ width: "100%" }}>
                    <Form.Item
                      name="delayInHours"
                      label="Hours"
                      rules={[
                        {
                          type: "number",
                          min: 0,
                          max: 24,
                          message: "Hours must be between 0-24",
                        },
                      ]}
                    >
                      <InputNumber
                        min={0}
                        max={24}
                        style={{ width: "100%" }}
                        addonAfter="hours"
                      />
                    </Form.Item>

                    <Form.Item
                      name="delayInMinutes"
                      label="Minutes"
                      rules={[
                        {
                          type: "number",
                          min: 0,
                          max: 59,
                          message: "Minutes must be between 0-59",
                        },
                      ]}
                    >
                      <InputNumber
                        min={0}
                        max={59}
                        style={{ width: "100%" }}
                        addonAfter="minutes"
                      />
                    </Form.Item>

                    <Form.Item
                      name="delayInSeconds"
                      label="Seconds"
                      rules={[
                        {
                          type: "number",
                          min: 0,
                          max: 59,
                          message: "Seconds must be between 0-59",
                        },
                      ]}
                    >
                      <InputNumber
                        min={0}
                        max={59}
                        style={{ width: "100%" }}
                        addonAfter="seconds"
                      />
                    </Form.Item>
                  </Space>
                </Card>
              );
            }

            if (delayType === "until_time") {
              return (
                <Card
                  title="Until Specific Time"
                  size="small"
                  style={{ marginBottom: 16 }}
                >
                  <Form.Item
                    name="delayUntilTime"
                    label="Time"
                    rules={[{ required: true, message: "Please select time" }]}
                  >
                    <TimePicker
                      format="HH:mm"
                      style={{ width: "100%" }}
                      placeholder="Select time"
                    />
                  </Form.Item>
                </Card>
              );
            }

            if (delayType === "until_date") {
              return (
                <Card
                  title="Until Specific Date"
                  size="small"
                  style={{ marginBottom: 16 }}
                >
                  <Form.Item
                    name="delayUntilDate"
                    label="Date"
                    rules={[{ required: true, message: "Please select date" }]}
                  >
                    <DatePicker
                      style={{ width: "100%" }}
                      placeholder="Select date"
                    />
                  </Form.Item>
                </Card>
              );
            }

            if (delayType === "random") {
              return (
                <Card
                  title="Random Duration"
                  size="small"
                  style={{ marginBottom: 16 }}
                >
                  <Form.Item
                    name="minDelay"
                    label="Minimum Delay (seconds)"
                    rules={[
                      { required: true, message: "Please enter minimum delay" },
                      {
                        type: "number",
                        min: 1,
                        message: "Minimum delay must be at least 1 second",
                      },
                    ]}
                  >
                    <InputNumber
                      min={1}
                      style={{ width: "100%" }}
                      addonAfter="seconds"
                    />
                  </Form.Item>

                  <Form.Item
                    name="maxDelay"
                    label="Maximum Delay (seconds)"
                    rules={[
                      { required: true, message: "Please enter maximum delay" },
                      {
                        type: "number",
                        min: 1,
                        message: "Maximum delay must be at least 1 second",
                      },
                    ]}
                  >
                    <InputNumber
                      min={1}
                      style={{ width: "100%" }}
                      addonAfter="seconds"
                    />
                  </Form.Item>
                </Card>
              );
            }

            return null;
          }}
        </Form.Item>

        {/* Progress Settings */}
        <Card
          title="Progress Settings"
          size="small"
          style={{ marginBottom: 16 }}
        >
          <Form.Item
            name="showProgress"
            label="Show Progress Indicator"
            valuePropName="checked"
          >
            <Switch />
          </Form.Item>

          <Form.Item
            name="progressMessage"
            label="Progress Message"
            rules={[
              { required: true, message: "Please enter progress message" },
            ]}
          >
            <Input
              placeholder="e.g., Please wait..."
              maxLength={100}
              showCount
            />
          </Form.Item>

          <Form.Item
            name="completionMessage"
            label="Completion Message (Optional)"
            tooltip="Message sent when delay completes"
          >
            <Input
              placeholder="e.g., Delay completed! Proceeding..."
              maxLength={100}
              showCount
            />
          </Form.Item>
        </Card>

        {/* Skip Conditions - Grayed out with Coming Soon tag */}
        <Card
          title={
            <Space>
              <span>Skip Conditions</span>
              <Tag color="orange">Coming Soon</Tag>
            </Space>
          }
          size="small"
          style={{
            marginBottom: 16,
            opacity: 0.6,
            pointerEvents: "none",
          }}
        >
          <Form.Item
            name="skipCondition"
            label="Allow Skipping"
            valuePropName="checked"
          >
            <Switch disabled />
          </Form.Item>

          <Form.Item
            noStyle
            shouldUpdate={(prevValues, currentValues) =>
              prevValues.skipCondition !== currentValues.skipCondition
            }
          >
            {({ getFieldValue }) => {
              const skipCondition = getFieldValue("skipCondition");

              if (skipCondition) {
                return (
                  <Form.Item
                    name="skipConditionType"
                    label="Skip Condition"
                    rules={[
                      {
                        required: true,
                        message: "Please select skip condition",
                      },
                    ]}
                  >
                    <Select disabled>
                      {getSkipConditionOptions().map((option) => (
                        <Select.Option key={option.value} value={option.value}>
                          {option.label}
                        </Select.Option>
                      ))}
                    </Select>
                  </Form.Item>
                );
              }
              return null;
            }}
          </Form.Item>
        </Card>

        {/* Preview Section */}
        <Card title="Preview" size="small">
          <Form.Item
            noStyle
            shouldUpdate={(prevValues, currentValues) =>
              prevValues.delayType !== currentValues.delayType ||
              prevValues.delayInSeconds !== currentValues.delayInSeconds ||
              prevValues.delayInMinutes !== currentValues.delayInMinutes ||
              prevValues.delayInHours !== currentValues.delayInHours
            }
          >
            {({ getFieldValue }) => {
              const delayType = getFieldValue("delayType");
              const delayInSeconds = getFieldValue("delayInSeconds") || 0;
              const delayInMinutes = getFieldValue("delayInMinutes") || 0;
              const delayInHours = getFieldValue("delayInHours") || 0;
              const progressMessage = getFieldValue("progressMessage");

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
                      <Text strong>Delay Type:</Text> {delayType}
                    </div>
                    {delayType === "fixed" && (
                      <div>
                        <Text strong>Duration:</Text> {delayInHours}h{" "}
                        {delayInMinutes}m {delayInSeconds}s
                      </div>
                    )}
                    <div>
                      <Text strong>Progress Message:</Text> {progressMessage}
                    </div>
                    <div style={{ marginTop: 8 }}>
                      <Space>
                        <ClockCircleOutlined style={{ color: "#1890ff" }} />
                        <Text type="secondary">{progressMessage}</Text>
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

export default ReactFlowDelayModule;
