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
  TimePicker,
  DatePicker,
  Radio,
  Tag,
  Divider,
  Row,
  Col,
  Checkbox,
} from "antd";
import {
  CalendarOutlined,
  ClockCircleOutlined,
  CheckCircleOutlined,
  CloseCircleOutlined,
  SaveOutlined,
  GlobalOutlined,
} from "@ant-design/icons";
import dayjs from "dayjs";

const { Text, Paragraph } = Typography;
const { Option } = Select;
const { RangePicker } = DatePicker;

const DAYS = [
  { key: "monday", label: "Monday" },
  { key: "tuesday", label: "Tuesday" },
  { key: "wednesday", label: "Wednesday" },
  { key: "thursday", label: "Thursday" },
  { key: "friday", label: "Friday" },
  { key: "saturday", label: "Saturday" },
  { key: "sunday", label: "Sunday" },
];

const TIMEZONES = [
  { value: "Asia/Kolkata", label: "Asia/Kolkata (IST +5:30)" },
  { value: "UTC", label: "UTC (+0:00)" },
  { value: "Asia/Dubai", label: "Asia/Dubai (GST +4:00)" },
  { value: "Asia/Singapore", label: "Asia/Singapore (SGT +8:00)" },
  { value: "Europe/London", label: "Europe/London (GMT/BST)" },
  { value: "America/New_York", label: "America/New_York (EST/EDT -5:00)" },
  { value: "America/Los_Angeles", label: "America/Los_Angeles (PST/PDT -8:00)" },
  { value: "Australia/Sydney", label: "Australia/Sydney (AEST +10:00)" },
];

const ReactFlowDateTimeConditionModule = ({
  visible,
  onClose,
  onSave,
  nodeData = null,
}) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [mode, setMode] = useState("business_hours");
  const [enabledDays, setEnabledDays] = useState([
    "monday",
    "tuesday",
    "wednesday",
    "thursday",
    "friday",
  ]);

  useEffect(() => {
    if (visible && nodeData) {
      const initialMode =
        nodeData.mode || nodeData.conditionType || "business_hours";
      setMode(initialMode);
      setEnabledDays(
        nodeData.enabled_days || [
          "monday",
          "tuesday",
          "wednesday",
          "thursday",
          "friday",
        ]
      );
      form.setFieldsValue({
        label: nodeData.label || "Business Hours & Schedule",
        mode: initialMode,
        timezone: nodeData.timezone || "Asia/Kolkata",
        start_time: nodeData.start_time || "09:00",
        end_time: nodeData.end_time || "18:00",
        enabled_days: nodeData.enabled_days || [
          "monday",
          "tuesday",
          "wednesday",
          "thursday",
          "friday",
        ],
        holidays_text: Array.isArray(nodeData.holidays)
          ? nodeData.holidays.join(", ")
          : nodeData.holidays_text || "",
      });
    } else if (visible) {
      form.resetFields();
      setMode("business_hours");
      setEnabledDays(["monday", "tuesday", "wednesday", "thursday", "friday"]);
      form.setFieldsValue({
        label: "Business Hours & Schedule",
        mode: "business_hours",
        timezone: "Asia/Kolkata",
        start_time: "09:00",
        end_time: "18:00",
        enabled_days: [
          "monday",
          "tuesday",
          "wednesday",
          "thursday",
          "friday",
        ],
        holidays_text: "",
      });
    }
  }, [visible, nodeData, form]);

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      const holidays = values.holidays_text
        ? values.holidays_text
            .split(",")
            .map((h) => h.trim())
            .filter(Boolean)
        : [];

      const savedData = {
        ...values,
        mode,
        conditionType: mode,
        condition_type: mode,
        enabled_days: enabledDays,
        holidays,
        label:
          values.label ||
          (mode === "business_hours"
            ? `Business Hours (${values.start_time} - ${values.end_time})`
            : `Time Condition - ${mode}`),
      };

      onSave(savedData);
      message.success("Business hours condition saved successfully!");
      onClose();
    } catch (error) {
      console.error("Validation failed:", error);
    } finally {
      setLoading(false);
    }
  };

  const toggleDay = (dayKey) => {
    if (enabledDays.includes(dayKey)) {
      setEnabledDays(enabledDays.filter((d) => d !== dayKey));
    } else {
      setEnabledDays([...enabledDays, dayKey]);
    }
  };

  return (
    <BuilderDrawer
      title={
        <BuilderDrawerTitle icon={<CalendarOutlined />}>
          Business Hours & Time Branching
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
            icon={<SaveOutlined />}
            loading={loading}
            onClick={handleSave}
            style={{ background: "#22c55e", borderColor: "#22c55e" }}
          >
            Save Condition
          </Button>
        </Space>
      }
    >
      <Form form={form} layout="vertical">
        <Form.Item
          name="label"
          label="Step Label"
          rules={[{ required: true, message: "Please provide a label" }]}
        >
          <Input placeholder="e.g. Check Business Hours" />
        </Form.Item>

        <Form.Item
          name="timezone"
          label={
            <span>
              <GlobalOutlined style={{ marginRight: 6 }} /> Timezone
            </span>
          }
          rules={[{ required: true, message: "Select a timezone" }]}
        >
          <Select showSearch optionFilterProp="label">
            {TIMEZONES.map((tz) => (
              <Option key={tz.value} value={tz.value} label={tz.label}>
                {tz.label}
              </Option>
            ))}
          </Select>
        </Form.Item>

        <Form.Item label="Condition Mode">
          <Radio.Group
            value={mode}
            onChange={(e) => setMode(e.target.value)}
            buttonStyle="solid"
            style={{ width: "100%", display: "flex" }}
          >
            <Radio.Button value="business_hours" style={{ flex: 1, textAlign: "center" }}>
              Business Hours
            </Radio.Button>
            <Radio.Button value="time_range" style={{ flex: 1, textAlign: "center" }}>
              Time Range
            </Radio.Button>
            <Radio.Button value="days_of_week" style={{ flex: 1, textAlign: "center" }}>
              Days of Week
            </Radio.Button>
          </Radio.Group>
        </Form.Item>

        {mode === "business_hours" && (
          <Card size="small" style={{ marginBottom: 16, borderRadius: 8, background: "#f8fafc" }}>
            <div style={{ fontWeight: 600, marginBottom: 12, display: "flex", alignItems: "center", gap: 6 }}>
              <ClockCircleOutlined style={{ color: "#22c55e" }} /> Working Hours (Start - End)
            </div>
            <Row gutter={12}>
              <Col span={12}>
                <Form.Item
                  name="start_time"
                  label="Opening Time"
                  rules={[{ required: true, message: "Required" }]}
                >
                  <Input placeholder="09:00" prefix={<ClockCircleOutlined />} />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item
                  name="end_time"
                  label="Closing Time"
                  rules={[{ required: true, message: "Required" }]}
                >
                  <Input placeholder="18:00" prefix={<ClockCircleOutlined />} />
                </Form.Item>
              </Col>
            </Row>

            <Divider style={{ margin: "12px 0" }} />

            <div style={{ fontWeight: 600, marginBottom: 8 }}>Working Days</div>
            <Space wrap size={[6, 8]}>
              {DAYS.map((d) => {
                const isActive = enabledDays.includes(d.key);
                return (
                  <Button
                    key={d.key}
                    size="small"
                    type={isActive ? "primary" : "default"}
                    onClick={() => toggleDay(d.key)}
                    style={
                      isActive
                        ? { background: "#22c55e", borderColor: "#22c55e", borderRadius: 4 }
                        : { borderRadius: 4 }
                    }
                  >
                    {d.label.slice(0, 3)}
                  </Button>
                );
              })}
            </Space>

            <Divider style={{ margin: "12px 0" }} />

            <Form.Item
              name="holidays_text"
              label="Holidays / Closed Dates (Optional)"
              help="Comma separated dates (e.g. 2026-12-25, 2026-01-01, 12-25)"
            >
              <Input placeholder="YYYY-MM-DD or MM-DD" />
            </Form.Item>
          </Card>
        )}

        {mode === "time_range" && (
          <Card size="small" style={{ marginBottom: 16, borderRadius: 8, background: "#f8fafc" }}>
            <Row gutter={12}>
              <Col span={12}>
                <Form.Item
                  name="start_time"
                  label="Start Time"
                  rules={[{ required: true, message: "Required" }]}
                >
                  <Input placeholder="09:00" prefix={<ClockCircleOutlined />} />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item
                  name="end_time"
                  label="End Time"
                  rules={[{ required: true, message: "Required" }]}
                >
                  <Input placeholder="18:00" prefix={<ClockCircleOutlined />} />
                </Form.Item>
              </Col>
            </Row>
          </Card>
        )}

        {mode === "days_of_week" && (
          <Card size="small" style={{ marginBottom: 16, borderRadius: 8, background: "#f8fafc" }}>
            <div style={{ fontWeight: 600, marginBottom: 10 }}>Select Active Days</div>
            <Space wrap size={[6, 8]}>
              {DAYS.map((d) => {
                const isActive = enabledDays.includes(d.key);
                return (
                  <Button
                    key={d.key}
                    size="small"
                    type={isActive ? "primary" : "default"}
                    onClick={() => toggleDay(d.key)}
                    style={
                      isActive
                        ? { background: "#22c55e", borderColor: "#22c55e", borderRadius: 4 }
                        : { borderRadius: 4 }
                    }
                  >
                    {d.label}
                  </Button>
                );
              })}
            </Space>
          </Card>
        )}

        {/* Branch Navigation Guide */}
        <Card size="small" style={{ background: "#f0fdf4", borderColor: "#bbf7d0", borderRadius: 8 }}>
          <div style={{ fontWeight: 600, color: "#166534", marginBottom: 6 }}>
            🔀 Output Handles on Node:
          </div>
          <div style={{ fontSize: 12, color: "#15803d", display: "flex", flexDirection: "column", gap: 4 }}>
            <div>
              <Tag color="green" style={{ fontWeight: 600 }}>
                OPEN / YES
              </Tag>{" "}
              Triggered when message arrives during business hours / active time.
            </div>
            <div>
              <Tag color="volcano" style={{ fontWeight: 600 }}>
                CLOSED / NO
              </Tag>{" "}
              Triggered outside business hours or during holidays / off-time.
            </div>
          </div>
        </Card>
      </Form>
    </BuilderDrawer>
  );
};

export default ReactFlowDateTimeConditionModule;
