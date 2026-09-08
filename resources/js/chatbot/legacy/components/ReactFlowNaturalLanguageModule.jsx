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
  Radio,
  Tag,
  Divider,
  Row,
  Col,
  Alert,
} from "antd";
import {
  RobotOutlined,
  SendOutlined,
  SaveOutlined,
  DatabaseOutlined,
  BranchesOutlined,
  SettingOutlined,
  ThunderboltOutlined,
} from "@ant-design/icons";

const { Text, Paragraph } = Typography;
const { TextArea } = Input;
const { Option } = Select;

const DEFAULT_INTENTS = ["sales", "support", "pricing", "general"];

const ReactFlowNaturalLanguageModule = ({
  visible,
  onClose,
  onSave,
  nodeData = null,
  aiBots = [],
  variables = [],
}) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [mode, setMode] = useState("knowledge_base");
  const [sendDirectly, setSendDirectly] = useState(true);

  useEffect(() => {
    if (visible && nodeData) {
      const initialMode = nodeData.mode || "knowledge_base";
      setMode(initialMode);
      setSendDirectly(
        nodeData.sendDirectly !== undefined ? nodeData.sendDirectly : true
      );

      form.setFieldsValue({
        label: nodeData.label || "AI Natural Language",
        mode: initialMode,
        ai_bot_id: nodeData.ai_bot_id || (aiBots[0]?.id ?? null),
        inputSource: nodeData.inputSource || "last_reply",
        inputVariable: nodeData.inputVariable || "",
        customPrompt: nodeData.customPrompt || "",
        sendDirectly:
          nodeData.sendDirectly !== undefined ? nodeData.sendDirectly : true,
        outputVariable: nodeData.outputVariable || "_ai_response",
        intents_text: Array.isArray(nodeData.intents)
          ? nodeData.intents.join(", ")
          : DEFAULT_INTENTS.join(", "),
      });
    } else if (visible) {
      form.resetFields();
      setMode("knowledge_base");
      setSendDirectly(true);

      const defaultBotId =
        aiBots.find((b) => b.is_default)?.id || aiBots[0]?.id || null;

      form.setFieldsValue({
        label: "AI Natural Language",
        mode: "knowledge_base",
        ai_bot_id: defaultBotId,
        inputSource: "last_reply",
        inputVariable: "",
        customPrompt: "",
        sendDirectly: true,
        outputVariable: "_ai_response",
        intents_text: DEFAULT_INTENTS.join(", "),
      });
    }
  }, [visible, nodeData, aiBots, form]);

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      const intents = values.intents_text
        ? values.intents_text
            .split(",")
            .map((i) => i.trim().toLowerCase())
            .filter(Boolean)
        : DEFAULT_INTENTS;

      const selectedBot = aiBots.find((b) => b.id === values.ai_bot_id);
      const botName = selectedBot ? selectedBot.name : "AI Assistant";

      const savedData = {
        ...values,
        mode,
        sendDirectly,
        intents,
        botName,
        label:
          values.label ||
          (mode === "intent_classification"
            ? "AI Intent Classifier"
            : `AI Response (${botName})`),
      };

      onSave(savedData);
      message.success("AI Natural Language node configured successfully!");
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
        <BuilderDrawerTitle icon={<RobotOutlined />}>
          AI & Natural Language Processing
        </BuilderDrawerTitle>
      }
      width={490}
      open={visible}
      onClose={onClose}
      footer={
        <Space style={{ width: "100%", justifyContent: "flex-end" }}>
          <Button onClick={onClose}>Cancel</Button>
          <Button
            type="primary"
            icon={<SaveOutlined />}
            loading={loading}
            onClick={handleSave}
            style={{ background: "#22c55e", borderColor: "#22c55e" }}
          >
            Save Configuration
          </Button>
        </Space>
      }
    >
      <Form form={form} layout="vertical">
        <Form.Item
          name="label"
          label="Step Label"
          rules={[{ required: true, message: "Please provide a step label" }]}
        >
          <Input placeholder="e.g. AI Customer Support / Answer Query" />
        </Form.Item>

        <Form.Item label="Operation Mode">
          <Radio.Group
            value={mode}
            onChange={(e) => setMode(e.target.value)}
            buttonStyle="solid"
            style={{ width: "100%", display: "flex" }}
          >
            <Radio.Button
              value="knowledge_base"
              style={{ flex: 1, textAlign: "center" }}
            >
              Knowledge Base
            </Radio.Button>
            <Radio.Button
              value="custom_prompt"
              style={{ flex: 1, textAlign: "center" }}
            >
              Custom Prompt
            </Radio.Button>
            <Radio.Button
              value="intent_classification"
              style={{ flex: 1, textAlign: "center" }}
            >
              Intent Classifier
            </Radio.Button>
          </Radio.Group>
        </Form.Item>

        {/* AI Bot Selection */}
        <Card
          size="small"
          style={{ marginBottom: 16, borderRadius: 8, background: "#f8fafc" }}
        >
          <div
            style={{
              fontWeight: 600,
              marginBottom: 10,
              display: "flex",
              alignItems: "center",
              gap: 6,
              color: "#0f172a",
            }}
          >
            <DatabaseOutlined style={{ color: "#3b82f6" }} /> Select AI Bot /
            Knowledge Base
          </div>

          <Form.Item
            name="ai_bot_id"
            label="AI Bot Engine"
            help={
              aiBots.length === 0
                ? "No AI bots found. Configure your OpenAI / Gemini bot in AI Bot Manager."
                : "Select the AI bot with your business knowledge & prompt settings."
            }
          >
            <Select
              placeholder="Select an AI Bot"
              showSearch
              optionFilterProp="children"
            >
              {aiBots.map((bot) => (
                <Option key={bot.id} value={bot.id}>
                  {bot.name} ({bot.provider?.toUpperCase()} - {bot.chat_model})
                  {bot.is_default ? " ⭐ Default" : ""}
                  {bot.business_info_count > 0
                    ? ` • ${bot.business_info_count} docs`
                    : ""}
                </Option>
              ))}
            </Select>
          </Form.Item>

          <Form.Item
            name="inputSource"
            label="User Input Source"
            style={{ marginBottom: 8 }}
          >
            <Select>
              <Option value="last_reply">
                Latest User WhatsApp Message (_last_reply)
              </Option>
              <Option value="custom_variable">
                Custom Variable Value
              </Option>
            </Select>
          </Form.Item>

          <Form.Item
            noStyle
            shouldUpdate={(prev, cur) => prev.inputSource !== cur.inputSource}
          >
            {({ getFieldValue }) =>
              getFieldValue("inputSource") === "custom_variable" ? (
                <Form.Item
                  name="inputVariable"
                  label="Variable Name"
                  rules={[{ required: true, message: "Required" }]}
                >
                  <Input placeholder="e.g. user_query or question" />
                </Form.Item>
              ) : null
            }
          </Form.Item>
        </Card>

        {/* Prompt Customization */}
        {(mode === "knowledge_base" || mode === "custom_prompt") && (
          <Card
            size="small"
            style={{ marginBottom: 16, borderRadius: 8, background: "#f8fafc" }}
          >
            <Form.Item
              name="customPrompt"
              label={
                mode === "knowledge_base"
                  ? "Additional Prompt Instructions (Optional)"
                  : "System Prompt / Instructions"
              }
              help="You can use {{variables}} like {{contact.name}} in the prompt."
            >
              <TextArea
                rows={3}
                placeholder={
                  mode === "knowledge_base"
                    ? "e.g. Focus on pricing and always mention our discount code SAVE10."
                    : "e.g. You are a friendly WhatsApp assistant. Answer clearly and concisely."
                }
              />
            </Form.Item>

            <Divider style={{ margin: "12px 0" }} />

            <Row gutter={12} align="middle">
              <Col span={16}>
                <div style={{ fontWeight: 600, fontSize: 13 }}>
                  Send AI Reply to User Directly
                </div>
                <div style={{ fontSize: 11, color: "#64748b" }}>
                  If enabled, bot will send the response to the WhatsApp chat immediately.
                </div>
              </Col>
              <Col span={8} style={{ textAlign: "right" }}>
                <Switch
                  checked={sendDirectly}
                  onChange={(checked) => setSendDirectly(checked)}
                />
              </Col>
            </Row>

            <Divider style={{ margin: "12px 0" }} />

            <Form.Item
              name="outputVariable"
              label="Save Response to Variable"
              help="Downstream nodes can reference this via {{_ai_response}}"
              style={{ marginBottom: 0 }}
            >
              <Input placeholder="_ai_response" />
            </Form.Item>
          </Card>
        )}

        {/* Intent Classification Mode */}
        {mode === "intent_classification" && (
          <Card
            size="small"
            style={{ marginBottom: 16, borderRadius: 8, background: "#f8fafc" }}
          >
            <div
              style={{
                fontWeight: 600,
                marginBottom: 8,
                display: "flex",
                alignItems: "center",
                gap: 6,
                color: "#0f172a",
              }}
            >
              <BranchesOutlined style={{ color: "#8b5cf6" }} /> Defined Intent Branches
            </div>

            <Form.Item
              name="intents_text"
              label="Target Intent Categories"
              help="Comma separated categories (e.g. sales, support, pricing, general)"
            >
              <Input placeholder="sales, support, pricing, general" />
            </Form.Item>

            <Alert
              type="info"
              showIcon
              message="Smart Branching"
              description="The AI will classify the user's message and route the conversation to the matching branch handle (e.g. sales, support, or fallback)."
            />
          </Card>
        )}
      </Form>
    </BuilderDrawer>
  );
};

export default ReactFlowNaturalLanguageModule;
