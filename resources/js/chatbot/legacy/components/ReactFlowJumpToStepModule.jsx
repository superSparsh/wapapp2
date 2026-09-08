import React, { useState, useEffect } from "react";
import BuilderDrawer, { BuilderDrawerTitle } from "./BuilderDrawer.jsx";
import {
  Form,
  Input,
  Button,
  Space,
  Card,
  Typography,
  message,
  Select,
  Switch,
  InputNumber,
  Radio,
} from "antd";
import {
  ThunderboltOutlined,
  SendOutlined,
  SettingOutlined,
  ArrowRightOutlined,
} from "@ant-design/icons";

const { TextArea } = Input;
const { Text } = Typography;

const ReactFlowJumpToStepModule = ({
  visible,
  onClose,
  onSave,
  nodeData = null,
  availableSteps = [],
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
        jumpType: "direct",
        targetStep: "",
        targetStepId: "",
        condition: "",
        jumpCondition: "always",
        delayBeforeJump: 0,
        showMessage: false,
        jumpMessage: "",
        preserveContext: true,
      });
    }
  }, [visible, nodeData, form]);

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      // Process the form data
      const targetStepName =
        values.targetStep ||
        availableSteps.find((s) => s.id === values.targetStepId)?.name ||
        values.targetStepId;

      const nodeData = {
        ...values,
        targetNodeId: values.targetStepId,
        target_node: values.targetStepId,
        targetStep: targetStepName,
        label: `Jump to: ${targetStepName}`,
      };

      onSave(nodeData);
      message.success("Jump to Step configured successfully!");
      onClose();
    } catch (error) {
      console.error("Form validation failed:", error);
    } finally {
      setLoading(false);
    }
  };

  const getJumpTypeOptions = () => [
    { value: "direct", label: "Direct Jump" },
    { value: "conditional", label: "Conditional Jump" },
    { value: "random", label: "Random Jump" },
    { value: "dynamic", label: "Dynamic Jump" },
  ];

  const getJumpConditionOptions = () => [
    { value: "always", label: "Always Jump" },
    { value: "on_success", label: "On Success" },
    { value: "on_error", label: "On Error" },
    { value: "on_timeout", label: "On Timeout" },
    { value: "custom", label: "Custom Condition" },
  ];

  const getTargetStepOptions = () => {
    return availableSteps.map((step) => ({
      value: step.id,
      label: `${step.name} (${step.type})`,
    }));
  };

  return (
    <BuilderDrawer
      title={
        <BuilderDrawerTitle icon={<ThunderboltOutlined />}>
          Jump to Step Configuration
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
          jumpType: "direct",
          targetStep: "",
          targetStepId: "",
          condition: "",
          jumpCondition: "always",
          delayBeforeJump: 0,
          showMessage: false,
          jumpMessage: "",
          preserveContext: true,
        }}
      >
        {/* Jump Type Selection */}
        <Card title="Jump Type" size="small" style={{ marginBottom: 16 }}>
          <Form.Item
            name="jumpType"
            label="Type of Jump"
            rules={[{ required: true, message: "Please select jump type" }]}
          >
            <Radio.Group>
              <Space direction="vertical" style={{ width: "100%" }}>
                {getJumpTypeOptions().map((option) => (
                  <Radio.Button
                    key={option.value}
                    value={option.value}
                    style={{ width: "100%", textAlign: "left" }}
                  >
                    <Space>
                      <ArrowRightOutlined />
                      {option.label}
                    </Space>
                  </Radio.Button>
                ))}
              </Space>
            </Radio.Group>
          </Form.Item>
        </Card>

        {/* Target Step Selection */}
        <Card title="Target Step" size="small" style={{ marginBottom: 16 }}>
          <Form.Item
            name="targetStepId"
            label="Select Target Step"
            rules={[{ required: true, message: "Please select target step" }]}
          >
            <Select
              placeholder="Choose a step to jump to"
              showSearch
              optionFilterProp="children"
              onChange={(value) => {
                const found = availableSteps.find((s) => s.id === value);
                if (found) {
                  form.setFieldsValue({ targetStep: found.name });
                }
              }}
            >
              {getTargetStepOptions().map((option) => (
                <Select.Option key={option.value} value={option.value}>
                  {option.label}
                </Select.Option>
              ))}
            </Select>
          </Form.Item>

          <Form.Item
            name="targetStep"
            label="Target Step Display Name"
          >
            <Input placeholder="Auto-populated or custom name" />
          </Form.Item>
        </Card>

        {/* Conditional Jump Settings */}
        <Form.Item
          noStyle
          shouldUpdate={(prevValues, currentValues) =>
            prevValues.jumpType !== currentValues.jumpType
          }
        >
          {({ getFieldValue }) => {
            const jumpType = getFieldValue("jumpType");

            if (jumpType === "conditional") {
              return (
                <Card
                  title="Jump Condition"
                  size="small"
                  style={{ marginBottom: 16 }}
                >
                  <Form.Item
                    name="jumpCondition"
                    label="Jump Condition"
                    rules={[
                      {
                        required: true,
                        message: "Please select jump condition",
                      },
                    ]}
                  >
                    <Select>
                      {getJumpConditionOptions().map((option) => (
                        <Select.Option key={option.value} value={option.value}>
                          {option.label}
                        </Select.Option>
                      ))}
                    </Select>
                  </Form.Item>

                  <Form.Item
                    noStyle
                    shouldUpdate={(prevValues, currentValues) =>
                      prevValues.jumpCondition !== currentValues.jumpCondition
                    }
                  >
                    {({ getFieldValue }) => {
                      const jumpCondition = getFieldValue("jumpCondition");

                      if (jumpCondition === "custom") {
                        return (
                          <Form.Item
                            name="condition"
                            label="Custom Condition"
                            rules={[
                              {
                                required: true,
                                message: "Please enter custom condition",
                              },
                            ]}
                          >
                            <TextArea
                              rows={3}
                              placeholder="Enter custom condition (e.g., user_response === 'yes')"
                            />
                          </Form.Item>
                        );
                      }
                      return null;
                    }}
                  </Form.Item>
                </Card>
              );
            }

            if (jumpType === "random") {
              return (
                <Card
                  title="Random Jump Settings"
                  size="small"
                  style={{ marginBottom: 16 }}
                >
                  <Form.Item
                    name="randomSteps"
                    label="Random Steps"
                    rules={[
                      { required: true, message: "Please select random steps" },
                    ]}
                  >
                    <Select
                      mode="multiple"
                      placeholder="Select multiple steps for random jump"
                      showSearch
                      optionFilterProp="children"
                    >
                      {getTargetStepOptions().map((option) => (
                        <Select.Option key={option.value} value={option.value}>
                          {option.label}
                        </Select.Option>
                      ))}
                    </Select>
                  </Form.Item>
                </Card>
              );
            }

            if (jumpType === "dynamic") {
              return (
                <Card
                  title="Dynamic Jump Settings"
                  size="small"
                  style={{ marginBottom: 16 }}
                >
                  <Form.Item
                    name="dynamicVariable"
                    label="Dynamic Variable"
                    rules={[
                      {
                        required: true,
                        message: "Please enter dynamic variable",
                      },
                    ]}
                  >
                    <Input placeholder="e.g., next_step, user_choice" />
                  </Form.Item>

                  <Form.Item
                    name="stepMapping"
                    label="Step Mapping"
                    rules={[
                      { required: true, message: "Please enter step mapping" },
                    ]}
                  >
                    <TextArea
                      rows={4}
                      placeholder='Enter step mapping (JSON format): {"step1": "welcome", "step2": "menu"}'
                    />
                  </Form.Item>
                </Card>
              );
            }

            return null;
          }}
        </Form.Item>

        {/* Jump Settings */}
        <Card title="Jump Settings" size="small" style={{ marginBottom: 16 }}>
          <Form.Item
            name="delayBeforeJump"
            label="Delay Before Jump (seconds)"
            rules={[
              {
                type: "number",
                min: 0,
                max: 60,
                message: "Delay must be between 0-60 seconds",
              },
            ]}
          >
            <InputNumber
              min={0}
              max={60}
              style={{ width: "100%" }}
              addonAfter="seconds"
            />
          </Form.Item>

          <Form.Item
            name="showMessage"
            label="Show Jump Message"
            valuePropName="checked"
          >
            <Switch />
          </Form.Item>

          <Form.Item
            noStyle
            shouldUpdate={(prevValues, currentValues) =>
              prevValues.showMessage !== currentValues.showMessage
            }
          >
            {({ getFieldValue }) => {
              const showMessage = getFieldValue("showMessage");

              if (showMessage) {
                return (
                  <Form.Item
                    name="jumpMessage"
                    label="Jump Message"
                    rules={[
                      { required: true, message: "Please enter jump message" },
                    ]}
                  >
                    <TextArea
                      rows={3}
                      placeholder="Enter message to show before jumping"
                      maxLength={500}
                    />
                  </Form.Item>
                );
              }
              return null;
            }}
          </Form.Item>

          <Form.Item
            name="preserveContext"
            label="Preserve Context"
            valuePropName="checked"
          >
            <Switch />
          </Form.Item>
        </Card>

        {/* Preview Section */}
        <Card title="Preview" size="small">
          <Form.Item
            noStyle
            shouldUpdate={(prevValues, currentValues) =>
              prevValues.jumpType !== currentValues.jumpType ||
              prevValues.targetStep !== currentValues.targetStep ||
              prevValues.jumpCondition !== currentValues.jumpCondition
            }
          >
            {({ getFieldValue }) => {
              const jumpType = getFieldValue("jumpType");
              const targetStep = getFieldValue("targetStep");
              const jumpCondition = getFieldValue("jumpCondition");

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
                      <Text strong>Jump Type:</Text> {jumpType}
                    </div>
                    <div>
                      <Text strong>Target Step:</Text>{" "}
                      {targetStep || "Not selected"}
                    </div>
                    {jumpType === "conditional" && (
                      <div>
                        <Text strong>Condition:</Text> {jumpCondition}
                      </div>
                    )}
                    <div style={{ marginTop: 8 }}>
                      <Space>
                        <ArrowRightOutlined style={{ color: "#1890ff" }} />
                        <Text type="secondary">
                          Will jump to: {targetStep || "selected step"}
                        </Text>
                      </Space>
                    </div>
                  </div>
                </div>
              );
            }}
          </Form.Item>
        </Card>

        {/* Save Button Inside Form */}
        <div style={{ marginTop: 24, textAlign: "right" }}>
          <Space>
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
        </div>
      </Form>
    </BuilderDrawer>
  );
};

export default ReactFlowJumpToStepModule;
