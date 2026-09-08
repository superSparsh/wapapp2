import React, { useState, useEffect } from "react";
import BuilderDrawer, { BuilderDrawerTitle } from "./BuilderDrawer.jsx";
import {
  Form,
  Input,
  Button,
  Space,
  message,
  Card,
  Select,
  Typography,
  Row,
  Col,
  Divider,
  Switch,
  InputNumber,
  Alert,
} from "antd";
import {
  ClockCircleOutlined,
  MessageOutlined,
  SettingOutlined,
  BranchesOutlined,
  DeleteOutlined,
  PlusOutlined,
} from "@ant-design/icons";

const { TextArea } = Input;
const { Text, Paragraph } = Typography;
const { Option } = Select;

const ReactFlowWaitForResponseModule = ({
  visible,
  onClose,
  onSave,
  nodeData = null,
  variables = [],
}) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  // Initialize form with node data if editing
  useEffect(() => {
    if (visible && nodeData) {
      form.setFieldsValue({
        timeout: nodeData.timeout || 300,
        enableTimeout: nodeData.enableTimeout !== false,
        timeoutAction: nodeData.timeoutAction || "continue",
        expectedResponses: nodeData.expectedResponses || [],
        enableResponseMatching: nodeData.enableResponseMatching !== false,
        matchType: nodeData.matchType || "exact",
        caseSensitive: nodeData.caseSensitive || false,

        customFallbackMessage: nodeData.customFallbackMessage || "",
        enableQuickReplies: nodeData.enableQuickReplies || false,
        quickReplyMessage: nodeData.quickReplyMessage || "",
        quickReplies: nodeData.quickReplies || [],
      });
    } else if (visible) {
      // Reset form for new node
      form.setFieldsValue({
        timeout: 300,
        enableTimeout: true,
        timeoutAction: "continue",
        expectedResponses: [],
        enableResponseMatching: true,
        matchType: "exact",
        caseSensitive: false,

        customFallbackMessage: "",
        enableQuickReplies: false,
        quickReplyMessage: "",
        quickReplies: [],
      });
    }
  }, [visible, nodeData, form]);

  const handleSave = async () => {
    try {
      setLoading(true);
      const values = await form.validateFields();

      const nodeData = {
        ...values,
        nodeType: "wait-for-response-node",
      };

      onSave(nodeData);
      message.success("Wait for Response node configured successfully!");
      onClose();
    } catch (error) {
      console.error("Validation failed:", error);
      message.error("Please check your configuration and try again.");
    } finally {
      setLoading(false);
    }
  };

  const addExpectedResponse = () => {
    const currentResponses = form.getFieldValue("expectedResponses") || [];
    form.setFieldsValue({
      expectedResponses: [
        ...currentResponses,
        { text: "", action: "continue" },
      ],
    });
  };

  const removeExpectedResponse = (index) => {
    const currentResponses = form.getFieldValue("expectedResponses") || [];
    const newResponses = currentResponses.filter((_, i) => i !== index);
    form.setFieldsValue({ expectedResponses: newResponses });
  };

  const addQuickReply = () => {
    const currentReplies = form.getFieldValue("quickReplies") || [];
    form.setFieldsValue({
      quickReplies: [...currentReplies, { text: "", action: "continue" }],
    });
  };

  const removeQuickReply = (index) => {
    const currentReplies = form.getFieldValue("quickReplies") || [];
    const newReplies = currentReplies.filter((_, i) => i !== index);
    form.setFieldsValue({ quickReplies: newReplies });
  };

  return (
    <BuilderDrawer
      title={
        <BuilderDrawerTitle icon={<ClockCircleOutlined />}>
          Wait for Response Configuration
        </BuilderDrawerTitle>
      }
      width={480}
      open={visible}
      onClose={onClose}
      footer={
        <Space style={{ width: "100%", justifyContent: "flex-end" }}>
          <Button onClick={onClose}>Cancel</Button>
          <Button type="primary" loading={loading} onClick={handleSave}>
            Save Configuration
          </Button>
        </Space>
      }
    >
      <Form
        form={form}
        layout="vertical"
        initialValues={{
          timeout: 300,
          enableTimeout: true,
          timeoutAction: "continue",
          expectedResponses: [],
          enableResponseMatching: true,
          matchType: "exact",
          caseSensitive: false,
          fallbackAction: "continue",
          customFallbackMessage: "",
          enableQuickReplies: false,
          quickReplyMessage: "",
          quickReplies: [],
        }}
      >
        {/* Timeout Configuration */}
        <Card
          title={
            <Space>
              <ClockCircleOutlined />
              <span>Timeout Settings</span>
            </Space>
          }
          size="small"
          style={{ marginBottom: 16 }}
        >
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                label="Enable Timeout"
                name="enableTimeout"
                valuePropName="checked"
              >
                <Switch />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                label="Timeout Duration"
                name="timeout"
                rules={[
                  {
                    required: true,
                    message: "Timeout duration is required",
                  },
                ]}
              >
                <InputNumber
                  min={10}
                  max={3600}
                  placeholder="300"
                  addonAfter="seconds"
                  style={{ width: "100%" }}
                />
              </Form.Item>
            </Col>
          </Row>

          <Form.Item
            label="Timeout Action"
            name="timeoutAction"
            rules={[
              {
                required: true,
                message: "Timeout action is required",
              },
            ]}
            help="What should happen when the timeout expires?"
          >
            <Select placeholder="Select timeout action">
              <Option value="continue">Continue to next node</Option>
              <Option value="repeat">Repeat the question</Option>
              <Option value="fallback">Send fallback message</Option>
              <Option value="end">End conversation</Option>
            </Select>
          </Form.Item>
        </Card>

        {/* Response Matching Configuration */}
        <Card
          title={
            <Space>
              <MessageOutlined />
              <span>Response Matching</span>
            </Space>
          }
          size="small"
          style={{ marginBottom: 16 }}
        >
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                label="Enable Response Matching"
                name="enableResponseMatching"
                valuePropName="checked"
              >
                <Switch />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                label="Match Type"
                name="matchType"
                rules={[
                  {
                    required: true,
                    message: "Match type is required",
                  },
                ]}
              >
                <Select placeholder="Select match type">
                  <Option value="exact">Exact match</Option>
                  <Option value="contains">Contains text</Option>
                  <Option value="regex">Regular expression</Option>
                  <Option value="fuzzy">Fuzzy match</Option>
                </Select>
              </Form.Item>
            </Col>
          </Row>

          <Form.Item
            label="Case Sensitive"
            name="caseSensitive"
            valuePropName="checked"
          >
            <Switch />
          </Form.Item>

          {/* Expected Responses */}
          <Form.Item label="Expected Responses">
            <Form.List name="expectedResponses">
              {(fields, { add, remove }) => (
                <>
                  {fields.map(({ key, name, ...restField }) => (
                    <Row gutter={8} key={key} style={{ marginBottom: 8 }}>
                      <Col span={10}>
                        <Form.Item
                          {...restField}
                          name={[name, "text"]}
                          rules={[
                            {
                              required: true,
                              message: "Response text is required",
                            },
                          ]}
                        >
                          <Input placeholder="Expected response text" />
                        </Form.Item>
                      </Col>
                      <Col span={10}>
                        <Form.Item
                          {...restField}
                          name={[name, "action"]}
                          rules={[
                            {
                              required: true,
                              message: "Action is required",
                            },
                          ]}
                        >
                          <Select placeholder="Select action">
                            <Option value="continue">Continue</Option>
                            <Option value="repeat">Repeat</Option>
                            <Option value="end">End</Option>
                          </Select>
                        </Form.Item>
                      </Col>
                      <Col span={4}>
                        <Button
                          type="text"
                          danger
                          onClick={() => remove(name)}
                          icon={<DeleteOutlined />}
                        />
                      </Col>
                    </Row>
                  ))}
                  <Button
                    type="dashed"
                    onClick={() => add()}
                    block
                    icon={<PlusOutlined />}
                  >
                    Add Expected Response
                  </Button>
                </>
              )}
            </Form.List>
          </Form.Item>
        </Card>

        {/* Quick Replies Configuration */}
        <Card
          title={
            <Space>
              <BranchesOutlined />
              <span>Quick Replies (Optional)</span>
            </Space>
          }
          size="small"
          style={{ marginBottom: 16 }}
        >
          <Form.Item
            label="Enable Quick Replies"
            name="enableQuickReplies"
            valuePropName="checked"
          >
            <Switch />
          </Form.Item>

          <Form.Item
            shouldUpdate={(prevValues, currentValues) =>
              prevValues.enableQuickReplies !== currentValues.enableQuickReplies
            }
          >
            {({ getFieldValue }) => {
              const enableQuickReplies = getFieldValue("enableQuickReplies");
              return (
                <Form.Item
                  label="Quick Reply Message"
                  name="quickReplyMessage"
                  help="This message will be displayed above the quick reply buttons. Leave empty to use the default message: 'Please select an option:'"
                >
                  <TextArea
                    rows={3}
                    placeholder="Examples:&#10;• What would you like to do?&#10;• Please choose an option:&#10;• How can I help you today?&#10;• Select your preference:"
                    disabled={!enableQuickReplies}
                  />
                </Form.Item>
              );
            }}
          </Form.Item>

          <Alert
            message="Quick Reply Limit"
            description="WhatsApp allows a maximum of 3 quick reply buttons per message. You can add up to 3 buttons below."
            type="info"
            showIcon
            style={{ marginBottom: 16 }}
          />

          <Form.Item label="Quick Reply Buttons">
            <Form.List name="quickReplies">
              {(fields, { add, remove }) => (
                <>
                  {fields.map(({ key, name, ...restField }) => (
                    <Row gutter={8} key={key} style={{ marginBottom: 8 }}>
                      <Col span={10}>
                        <Form.Item
                          {...restField}
                          name={[name, "text"]}
                          rules={[
                            {
                              required: true,
                              message: "Button text is required",
                            },
                          ]}
                        >
                          <Input placeholder="Button text" />
                        </Form.Item>
                      </Col>
                      <Col span={10}>
                        <Form.Item
                          {...restField}
                          name={[name, "action"]}
                          rules={[
                            {
                              required: true,
                              message: "Action is required",
                            },
                          ]}
                        >
                          <Select placeholder="Select action">
                            <Option value="continue">Continue</Option>
                            <Option value="repeat">Repeat</Option>
                            <Option value="end">End</Option>
                          </Select>
                        </Form.Item>
                      </Col>
                      <Col span={4}>
                        <Button
                          type="text"
                          danger
                          onClick={() => remove(name)}
                          icon={<DeleteOutlined />}
                        />
                      </Col>
                    </Row>
                  ))}
                  <Button
                    type="dashed"
                    onClick={() => add()}
                    block
                    icon={<PlusOutlined />}
                    disabled={fields.length >= 3}
                  >
                    {fields.length >= 3
                      ? "Maximum 3 quick reply buttons reached"
                      : `Add Quick Reply Button (${fields.length}/3)`}
                  </Button>
                </>
              )}
            </Form.List>
          </Form.Item>
        </Card>

        {/* Custom Fallback Message */}
        <Form.Item
          shouldUpdate={(prevValues, currentValues) =>
            prevValues.timeoutAction !== currentValues.timeoutAction
          }
        >
          {({ getFieldValue }) => {
            const timeoutAction = getFieldValue("timeoutAction");
            return (
              <Form.Item
                label="Custom Fallback Message"
                name="customFallbackMessage"
                help="This message will be sent when 'Use fallback message' is selected as timeout action"
              >
                <TextArea
                  rows={3}
                  placeholder="Enter custom fallback message..."
                  disabled={timeoutAction !== "fallback"}
                />
              </Form.Item>
            );
          }}
        </Form.Item>

        {/* Quick Reply Connection Instructions */}
        <Alert
          message="Quick Reply Connections"
          description={
            <div>
              <p>
                • When Quick Replies are enabled, green connection handles will
                appear on the node
              </p>
              <p>
                • Drag from these green handles to connect to target nodes for
                each quick reply
              </p>
              <p>
                • Each quick reply button can route to a different node in your
                flow
              </p>
              <p>
                • The default output handle (bottom) is used for expected
                responses and fallback actions
              </p>
            </div>
          }
          type="success"
          showIcon
          style={{ marginBottom: 16 }}
        />

        {/* Information Alert */}
        <Alert
          message="Wait for Response Node Information"
          description={
            <div>
              <p>
                • This node will pause the conversation and wait for user input
              </p>
              <p>
                • Configure timeout settings to handle cases where users don't
                respond
              </p>
              <p>
                • Set up expected responses to route the conversation based on
                user input
              </p>
              <p>
                • Quick replies can be used to provide predefined response
                options
              </p>
              <p>
                • Fallback actions ensure the conversation continues even with
                unexpected input
              </p>
            </div>
          }
          type="info"
          showIcon
          style={{ marginBottom: 16 }}
        />
      </Form>
    </BuilderDrawer>
  );
};

export default ReactFlowWaitForResponseModule;
