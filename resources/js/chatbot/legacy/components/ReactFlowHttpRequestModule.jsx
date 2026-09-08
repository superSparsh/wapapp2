import React, { useState, useEffect } from "react";
import BuilderDrawer, { BuilderDrawerTitle } from "./BuilderDrawer.jsx";
import {
  Form,
  Input,
  Button,
  Switch,
  Card,
  Alert,
  message,
  Space,
} from "antd";
import {
  ApiOutlined,
  InfoCircleOutlined,
  SendOutlined,
} from "@ant-design/icons";

const ReactFlowHttpRequestModule = ({ visible, onClose, nodeData, onSave }) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [testing, setTesting] = useState(false);

  useEffect(() => {
    if (visible && nodeData) {
      form.setFieldsValue({
        enabled: nodeData.enabled !== false,
        url: nodeData.url || "",
      });
    }
  }, [visible, nodeData, form]);

  const handleTestUrl = async () => {
    try {
      const values = await form.validateFields(["url"]);
      setTesting(true);

      // Create test data
      const testData = {
        user_data: {
          phone: "1234567890",
          name: "Test User",
          message: "This is a test message from chatbot webhook",
        },
        button_data: {
          button_text: "Test Button",
          button_id: "test_button",
          reply_type: "test",
        },
        flow_data: {
          node_id: "test-node-123",
          automation_id: "1",
          timestamp: new Date().toISOString(),
          message_id: "test-message-123",
        },
        complete_payload: {
          type: "test_interaction",
          data: "Test interaction data",
          timestamp: new Date().toISOString(),
        },
      };

      // Send test request
      const response = await fetch("/api/test-webhook", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content"),
        },
        body: JSON.stringify({
          url: values.url,
          testData: testData,
        }),
      });

      const result = await response.json();

      if (response.ok && result.success) {
        message.success(
          `Test successful! Response: ${result.status} - ${result.message}`
        );
      } else {
        message.error(`Test failed: ${result.message || "Unknown error"}`);
      }
    } catch (error) {
      console.error("Test failed:", error);
      message.error("Test failed: " + (error.message || "Network error"));
    } finally {
      setTesting(false);
    }
  };

  const handleSave = async () => {
    try {
      setLoading(true);
      const values = await form.validateFields();

      const updatedData = {
        ...nodeData,
        enabled: values.enabled,
        url: values.url,
        method: "POST", // Always POST
        contentType: "application/json", // Always JSON
        body: JSON.stringify(
          {
            user_data: {
              phone: "{{user_phone}}",
              name: "{{user_name}}",
              message: "{{user_message}}",
            },
            button_data: {
              button_text: "{{button_text}}",
              button_id: "{{button_id}}",
              reply_type: "{{reply_type}}",
            },
            flow_data: {
              node_id: "{{node_id}}",
              automation_id: "{{automation_id}}",
              timestamp: "{{timestamp}}",
              message_id: "{{message_id}}",
            },
            complete_payload: "{{complete_payload}}",
          },
          null,
          2
        ),
        timeout: 30, // Default timeout
        retryCount: 3, // Default retries
        errorHandling: "continue", // Always continue on error
        successCondition: "status_200", // Default success condition
        saveResponse: false, // Don't save response by default
        responseVariable: "",
      };

      onSave(updatedData);
      message.success("Webhook configured successfully!");
      onClose();
    } catch (error) {
      console.error("Validation failed:", error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <BuilderDrawer
      title={
        <BuilderDrawerTitle icon={<ApiOutlined />}>
          Webhook Configuration
        </BuilderDrawerTitle>
      }
      width={420}
      open={visible}
      onClose={onClose}
      footer={
        <div style={{ textAlign: "right" }}>
          <Button onClick={onClose} style={{ marginRight: 8 }}>
            Cancel
          </Button>
          <Button type="primary" onClick={handleSave} loading={loading}>
            Save Webhook
          </Button>
        </div>
      }
    >
      <Alert
        message="Simple Webhook Setup"
        description="Configure your webhook URL and the system will automatically send user data, button interactions, and flow information to your endpoint."
        type="info"
        showIcon
        icon={<InfoCircleOutlined />}
        style={{ marginBottom: 24 }}
      />

      <Form form={form} layout="vertical">
        {/* Enable/Disable Webhook */}
        <Card title="Webhook Status" size="small" style={{ marginBottom: 16 }}>
          <Form.Item
            name="enabled"
            valuePropName="checked"
            label="Enable Webhook"
          >
            <Switch />
          </Form.Item>
        </Card>

        {/* Webhook URL */}
        <Card title="Webhook Settings" size="small">
          <Form.Item
            name="url"
            label="Webhook URL"
            rules={[
              {
                required: true,
                message: "Please enter your webhook URL",
              },
              {
                type: "url",
                message: "Please enter a valid URL",
              },
            ]}
          >
            <Input
              placeholder="https://your-api.com/webhook"
              addonBefore="POST"
              style={{ fontFamily: "monospace" }}
            />
          </Form.Item>

          <Form.Item>
            <Button
              type="dashed"
              icon={<SendOutlined />}
              onClick={handleTestUrl}
              loading={testing}
              style={{ width: "100%" }}
            >
              {testing ? "Testing..." : "Test Webhook URL"}
            </Button>
          </Form.Item>
        </Card>

        {/* Data Structure Info */}
        <Card title="Data Structure" size="small" style={{ marginTop: 16 }}>
          <Alert
            message="Automatic Data Structure"
            description={
              <div>
                <p>
                  The webhook will automatically send the complete user
                  interaction data:
                </p>
                <pre
                  style={{
                    fontSize: "12px",
                    background: "#f5f5f5",
                    padding: 8,
                    borderRadius: 4,
                  }}
                >
                  {`{
  "user_data": {
    "phone": "1234567890",
    "name": "User Name", 
    "message": "User's message"
  },
  "button_data": {
    "button_text": "Button clicked",
    "button_id": "button_id",
    "reply_type": "interactive"
  },
  "flow_data": {
    "node_id": "httpRequest-123",
    "automation_id": "1",
    "timestamp": "2024-01-15T10:30:00Z",
    "message_id": "wamid.xxx"
  },
  "complete_payload": {
    // Complete raw payload from user interaction
    // Structure varies based on node type and user response
  }
}`}
                </pre>
                <p style={{ fontSize: "11px", color: "#666", marginTop: 8 }}>
                  <strong>Note:</strong> The complete_payload structure varies
                  based on the node type (Wait for Response, WhatsApp Flow,
                  etc.) and the user's actual response.
                </p>
              </div>
            }
            type="success"
            showIcon
          />
        </Card>
      </Form>
    </BuilderDrawer>
  );
};

export default ReactFlowHttpRequestModule;
