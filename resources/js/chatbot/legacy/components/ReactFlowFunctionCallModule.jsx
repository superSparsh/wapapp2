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
  Tabs,
} from "antd";
import {
  FunctionOutlined,
  SendOutlined,
  PlusOutlined,
  DeleteOutlined,
  CodeOutlined,
} from "@ant-design/icons";

const { TextArea } = Input;
const { Text } = Typography;

const ReactFlowFunctionCallModule = ({
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
        functionType: "predefined",
        functionName: "",
        customFunction: "",
        parameters: [{ key: "", value: "" }],
        returnVariable: "function_result",
        timeout: 30,
        retryCount: 3,
        saveResult: true,
        errorHandling: "continue",
        asyncExecution: false,
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
        label: `Function Call - ${values.functionName || values.functionType}`,
      };

      onSave(nodeData);
      message.success("Function Call configured successfully!");
      onClose();
    } catch (error) {
      console.error("Form validation failed:", error);
    } finally {
      setLoading(false);
    }
  };

  const getFunctionTypeOptions = () => [
    { value: "predefined", label: "Predefined Function" },
    { value: "custom", label: "Custom Function" },
    { value: "external", label: "External API Function" },
    { value: "database", label: "Database Function" },
  ];

  const getPredefinedFunctions = () => [
    {
      value: "send_email",
      label: "Send Email",
      description: "Send email notification",
    },
    {
      value: "send_sms",
      label: "Send SMS",
      description: "Send SMS notification",
    },
    {
      value: "create_user",
      label: "Create User",
      description: "Create new user account",
    },
    {
      value: "update_user",
      label: "Update User",
      description: "Update user information",
    },
    {
      value: "get_weather",
      label: "Get Weather",
      description: "Get weather information",
    },
    {
      value: "calculate_total",
      label: "Calculate Total",
      description: "Calculate order total",
    },
    {
      value: "validate_input",
      label: "Validate Input",
      description: "Validate user input",
    },
    {
      value: "format_date",
      label: "Format Date",
      description: "Format date string",
    },
    {
      value: "generate_id",
      label: "Generate ID",
      description: "Generate unique ID",
    },
    {
      value: "log_activity",
      label: "Log Activity",
      description: "Log user activity",
    },
  ];

  const getErrorHandlingOptions = () => [
    { value: "continue", label: "Continue on Error" },
    { value: "stop", label: "Stop Flow on Error" },
    { value: "retry", label: "Retry on Error" },
    { value: "fallback", label: "Use Fallback Value" },
  ];

  return (
    <BuilderDrawer
      title={
        <BuilderDrawerTitle icon={<FunctionOutlined />}>
          Function Call Configuration
        </BuilderDrawerTitle>
      }
      width={520}
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
          functionType: "predefined",
          functionName: "",
          customFunction: "",
          parameters: [{ key: "", value: "" }],
          returnVariable: "function_result",
          timeout: 30,
          retryCount: 3,
          saveResult: true,
          errorHandling: "continue",
          asyncExecution: false,
        }}
      >
        <Tabs
          defaultActiveKey="basic"
          items={[
            {
              key: "basic",
              label: "Basic Settings",
              children: (
                <>
                  {/* Function Type Selection */}
                  <Card
                    title="Function Type"
                    size="small"
                    style={{ marginBottom: 16 }}
                  >
                    <Form.Item
                      name="functionType"
                      label="Type of Function"
                      rules={[
                        {
                          required: true,
                          message: "Please select function type",
                        },
                      ]}
                    >
                      <Select>
                        {getFunctionTypeOptions().map((option) => (
                          <Select.Option
                            key={option.value}
                            value={option.value}
                          >
                            <Space>
                              <FunctionOutlined />
                              {option.label}
                            </Space>
                          </Select.Option>
                        ))}
                      </Select>
                    </Form.Item>
                  </Card>

                  {/* Function Selection */}
                  <Form.Item
                    noStyle
                    shouldUpdate={(prevValues, currentValues) =>
                      prevValues.functionType !== currentValues.functionType
                    }
                  >
                    {({ getFieldValue }) => {
                      const functionType = getFieldValue("functionType");

                      if (functionType === "predefined") {
                        return (
                          <Card
                            title="Predefined Function"
                            size="small"
                            style={{ marginBottom: 16 }}
                          >
                            <Form.Item
                              name="functionName"
                              label="Select Function"
                              rules={[
                                {
                                  required: true,
                                  message: "Please select a function",
                                },
                              ]}
                            >
                              <Select
                                placeholder="Choose a predefined function"
                                showSearch
                                optionFilterProp="children"
                              >
                                {getPredefinedFunctions().map((func) => (
                                  <Select.Option
                                    key={func.value}
                                    value={func.value}
                                  >
                                    <div>
                                      <div style={{ fontWeight: 500 }}>
                                        {func.label}
                                      </div>
                                      <div
                                        style={{
                                          fontSize: "12px",
                                          color: "#666",
                                        }}
                                      >
                                        {func.description}
                                      </div>
                                    </div>
                                  </Select.Option>
                                ))}
                              </Select>
                            </Form.Item>
                          </Card>
                        );
                      }

                      if (functionType === "custom") {
                        return (
                          <Card
                            title="Custom Function"
                            size="small"
                            style={{ marginBottom: 16 }}
                          >
                            <Form.Item
                              name="customFunction"
                              label="Custom Function Code"
                              rules={[
                                {
                                  required: true,
                                  message: "Please enter custom function",
                                },
                              ]}
                            >
                              <TextArea
                                rows={8}
                                placeholder="Enter your custom function code (JavaScript)"
                                showCount
                                maxLength={5000}
                              />
                            </Form.Item>
                          </Card>
                        );
                      }

                      if (functionType === "external") {
                        return (
                          <Card
                            title="External API Function"
                            size="small"
                            style={{ marginBottom: 16 }}
                          >
                            <Form.Item
                              name="apiEndpoint"
                              label="API Endpoint"
                              rules={[
                                {
                                  required: true,
                                  message: "Please enter API endpoint",
                                },
                              ]}
                            >
                              <Input placeholder="https://api.example.com/function" />
                            </Form.Item>

                            <Form.Item
                              name="apiMethod"
                              label="HTTP Method"
                              rules={[
                                {
                                  required: true,
                                  message: "Please select HTTP method",
                                },
                              ]}
                            >
                              <Select>
                                <Select.Option value="GET">GET</Select.Option>
                                <Select.Option value="POST">POST</Select.Option>
                                <Select.Option value="PUT">PUT</Select.Option>
                                <Select.Option value="DELETE">
                                  DELETE
                                </Select.Option>
                              </Select>
                            </Form.Item>
                          </Card>
                        );
                      }

                      if (functionType === "database") {
                        return (
                          <Card
                            title="Database Function"
                            size="small"
                            style={{ marginBottom: 16 }}
                          >
                            <Form.Item
                              name="databaseQuery"
                              label="Database Query"
                              rules={[
                                {
                                  required: true,
                                  message: "Please enter database query",
                                },
                              ]}
                            >
                              <TextArea
                                rows={4}
                                placeholder="Enter SQL query or database operation"
                                showCount
                                maxLength={2000}
                              />
                            </Form.Item>

                            <Form.Item
                              name="databaseType"
                              label="Database Type"
                              rules={[
                                {
                                  required: true,
                                  message: "Please select database type",
                                },
                              ]}
                            >
                              <Select>
                                <Select.Option value="mysql">
                                  MySQL
                                </Select.Option>
                                <Select.Option value="postgresql">
                                  PostgreSQL
                                </Select.Option>
                                <Select.Option value="mongodb">
                                  MongoDB
                                </Select.Option>
                                <Select.Option value="sqlite">
                                  SQLite
                                </Select.Option>
                              </Select>
                            </Form.Item>
                          </Card>
                        );
                      }

                      return null;
                    }}
                  </Form.Item>

                  {/* Execution Settings */}
                  <Card
                    title="Execution Settings"
                    size="small"
                    style={{ marginBottom: 16 }}
                  >
                    <Form.Item
                      name="asyncExecution"
                      label="Asynchronous Execution"
                      valuePropName="checked"
                    >
                      <Switch />
                    </Form.Item>

                    <Form.Item
                      name="timeout"
                      label="Timeout (seconds)"
                      rules={[
                        {
                          type: "number",
                          min: 1,
                          max: 300,
                          message: "Timeout must be between 1-300 seconds",
                        },
                      ]}
                    >
                      <InputNumber
                        min={1}
                        max={300}
                        style={{ width: "100%" }}
                        addonAfter="seconds"
                      />
                    </Form.Item>

                    <Form.Item
                      name="retryCount"
                      label="Retry Count"
                      rules={[
                        {
                          type: "number",
                          min: 0,
                          max: 10,
                          message: "Retry count must be between 0-10",
                        },
                      ]}
                    >
                      <InputNumber
                        min={0}
                        max={10}
                        style={{ width: "100%" }}
                        addonAfter="retries"
                      />
                    </Form.Item>
                  </Card>
                </>
              ),
            },
            {
              key: "parameters",
              label: "Parameters",
              children: (
                <Card title="Function Parameters" size="small">
                  <Form.List name="parameters">
                    {(fields, { add, remove }) => (
                      <>
                        {fields.map(({ key, name, ...restField }) => (
                          <div key={key} style={{ marginBottom: 8 }}>
                            <Space>
                              <Form.Item
                                {...restField}
                                name={[name, "key"]}
                                rules={[
                                  {
                                    required: true,
                                    message: "Parameter key required",
                                  },
                                ]}
                              >
                                <Input
                                  placeholder="Parameter Key"
                                  style={{ width: 150 }}
                                />
                              </Form.Item>
                              <Form.Item
                                {...restField}
                                name={[name, "value"]}
                                rules={[
                                  {
                                    required: true,
                                    message: "Parameter value required",
                                  },
                                ]}
                              >
                                <Input
                                  placeholder="Parameter Value"
                                  style={{ width: 200 }}
                                />
                              </Form.Item>
                              {fields.length > 1 && (
                                <Button
                                  type="text"
                                  danger
                                  icon={<DeleteOutlined />}
                                  onClick={() => remove(name)}
                                />
                              )}
                            </Space>
                          </div>
                        ))}
                        <Button
                          type="dashed"
                          onClick={() => add()}
                          block
                          icon={<PlusOutlined />}
                        >
                          Add Parameter
                        </Button>
                      </>
                    )}
                  </Form.List>
                </Card>
              ),
            },
            {
              key: "response",
              label: "Response Handling",
              children: (
                <>
                  {/* Response Settings */}
                  <Card
                    title="Response Settings"
                    size="small"
                    style={{ marginBottom: 16 }}
                  >
                    <Form.Item
                      name="saveResult"
                      label="Save Function Result"
                      valuePropName="checked"
                    >
                      <Switch />
                    </Form.Item>

                    <Form.Item
                      noStyle
                      shouldUpdate={(prevValues, currentValues) =>
                        prevValues.saveResult !== currentValues.saveResult
                      }
                    >
                      {({ getFieldValue }) => {
                        const saveResult = getFieldValue("saveResult");

                        if (saveResult) {
                          return (
                            <Form.Item
                              name="returnVariable"
                              label="Return Variable Name"
                              rules={[
                                {
                                  required: true,
                                  message: "Please enter variable name",
                                },
                              ]}
                            >
                              <Input placeholder="e.g., function_result" />
                            </Form.Item>
                          );
                        }
                        return null;
                      }}
                    </Form.Item>
                  </Card>

                  {/* Error Handling */}
                  <Card title="Error Handling" size="small">
                    <Form.Item
                      name="errorHandling"
                      label="Error Handling Strategy"
                      rules={[
                        {
                          required: true,
                          message: "Please select error handling strategy",
                        },
                      ]}
                    >
                      <Select>
                        {getErrorHandlingOptions().map((option) => (
                          <Select.Option
                            key={option.value}
                            value={option.value}
                          >
                            {option.label}
                          </Select.Option>
                        ))}
                      </Select>
                    </Form.Item>

                    <Form.Item name="fallbackValue" label="Fallback Value">
                      <Input placeholder="Enter fallback value if function fails" />
                    </Form.Item>
                  </Card>
                </>
              ),
            },
          ]}
        />

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

export default ReactFlowFunctionCallModule;
