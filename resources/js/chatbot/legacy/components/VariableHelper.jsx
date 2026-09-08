import React, { useState, useEffect } from "react";
import {
  Button,
  Select,
  Space,
  Tooltip,
  Modal,
  List,
  Input,
  Tag,
  Typography,
  Card,
  Spin,
  Empty,
  Divider,
} from "antd";
import {
  CodeOutlined,
  PlusOutlined,
  SearchOutlined,
  CopyOutlined,
  UserOutlined,
  ShopOutlined,
  CalendarOutlined,
  MessageOutlined,
} from "@ant-design/icons";
import axios from "axios";

const { Text } = Typography;

const VariableHelper = ({
  variables = [],
  onVariableSelect,
  placeholder = "Insert Variable",
  style = {},
  size = "middle",
}) => {
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [searchText, setSearchText] = useState("");
  const [builtInVariables, setBuiltInVariables] = useState([]);
  const [loading, setLoading] = useState(false);

  // Fetch built-in variables from API
  useEffect(() => {
    const fetchBuiltInVariables = async () => {
      setLoading(true);
      try {
        const csrfToken = localStorage.getItem("csrfToken");
        const response = await axios.get("/templates/variables/chatbot", {
          headers: {
            "X-CSRF-TOKEN": csrfToken,
          },
        });
        if (response.data.status === 1) {
          setBuiltInVariables(response.data.variables);
        }
      } catch (error) {
        console.error("Error fetching built-in variables:", error);
      } finally {
        setLoading(false);
      }
    };

    fetchBuiltInVariables();
  }, []);

  const handleVariableClick = (variable) => {
    const variableSyntax = variable.syntax || `{{${variable.name}}}`;
    onVariableSelect(variableSyntax);
    setIsModalVisible(false);
  };

  const handleCopyVariable = (variable) => {
    const variableSyntax = variable.syntax || `{{${variable.name}}}`;
    navigator.clipboard.writeText(variableSyntax);
  };

  // Combine built-in and custom variables
  const allVariables = [...builtInVariables, ...variables];

  const filteredVariables = allVariables.filter(
    (variable) =>
      variable.name?.toLowerCase().includes(searchText.toLowerCase()) ||
      variable.display_name?.toLowerCase().includes(searchText.toLowerCase()) ||
      variable.description?.toLowerCase().includes(searchText.toLowerCase())
  );

  const getCategoryIcon = (category) => {
    const icons = {
      message: <MessageOutlined />,
      subscriber: <UserOutlined />,
      business: <ShopOutlined />,
      datetime: <CalendarOutlined />,
    };
    return icons[category] || <CodeOutlined />;
  };

  const getCategoryColor = (category) => {
    const colors = {
      message: "blue",
      subscriber: "green",
      business: "purple",
      datetime: "orange",
    };
    return colors[category] || "default";
  };

  const groupVariablesByCategory = (variables) => {
    const grouped = {};
    variables.forEach((variable) => {
      const category = variable.category || "other";
      if (!grouped[category]) {
        grouped[category] = [];
      }
      grouped[category].push(variable);
    });
    return grouped;
  };

  const groupedVariables = groupVariablesByCategory(filteredVariables);

  return (
    <>
      <Tooltip title="Insert Variable">
        <Button
          icon={<CodeOutlined />}
          onClick={() => setIsModalVisible(true)}
          style={style}
          size={size}
        >
          {placeholder}
        </Button>
      </Tooltip>

      <Modal
        title={
          <Space>
            <CodeOutlined style={{ color: "#1890ff" }} />
            <span>Insert Variable</span>
            <Tag color="blue">{allVariables.length} variables available</Tag>
          </Space>
        }
        open={isModalVisible}
        onCancel={() => setIsModalVisible(false)}
        footer={null}
        width={700}
      >
        <div style={{ marginBottom: 16 }}>
          <Input
            placeholder="Search variables..."
            prefix={<SearchOutlined />}
            value={searchText}
            onChange={(e) => setSearchText(e.target.value)}
            allowClear
          />
        </div>

        {loading ? (
          <div style={{ textAlign: "center", padding: "40px" }}>
            <Spin size="large" />
            <div style={{ marginTop: 16 }}>Loading variables...</div>
          </div>
        ) : filteredVariables.length === 0 ? (
          <Empty
            description={
              <div>
                <div>No variables found</div>
                <div style={{ fontSize: "12px", color: "#999", marginTop: 8 }}>
                  {searchText
                    ? "Try adjusting your search terms"
                    : "No variables defined yet"}
                </div>
              </div>
            }
          />
        ) : (
          <div style={{ maxHeight: "400px", overflowY: "auto" }}>
            {Object.entries(groupedVariables).map(
              ([category, categoryVariables]) => (
                <div key={category}>
                  <Divider orientation="left">
                    <Space>
                      {getCategoryIcon(category)}
                      <span style={{ textTransform: "capitalize" }}>
                        {category} Variables
                      </span>
                      <Tag color={getCategoryColor(category)} size="small">
                        {categoryVariables.length}
                      </Tag>
                    </Space>
                  </Divider>

                  <List
                    dataSource={categoryVariables}
                    renderItem={(variable) => (
                      <List.Item
                        actions={[
                          <Tooltip title="Copy variable syntax">
                            <Button
                              type="text"
                              size="small"
                              icon={<CopyOutlined />}
                              onClick={() => handleCopyVariable(variable)}
                            />
                          </Tooltip>,
                        ]}
                        style={{
                          cursor: "pointer",
                          padding: "8px 16px",
                          borderRadius: "6px",
                          marginBottom: "4px",
                          border: "1px solid #f0f0f0",
                        }}
                        onClick={() => handleVariableClick(variable)}
                      >
                        <List.Item.Meta
                          title={
                            <Space>
                              <Text strong>
                                {variable.display_name || variable.name}
                              </Text>
                              <Tag
                                color={getCategoryColor(category)}
                                size="small"
                              >
                                {variable.type || "built-in"}
                              </Tag>
                            </Space>
                          }
                          description={
                            <div>
                              <Text
                                type="secondary"
                                style={{ fontSize: "12px" }}
                              >
                                {variable.description ||
                                  "No description available"}
                              </Text>
                              <br />
                              <Text
                                code
                                style={{ fontSize: "11px", marginTop: "4px" }}
                              >
                                {variable.syntax || `{{${variable.name}}}`}
                              </Text>
                            </div>
                          }
                        />
                      </List.Item>
                    )}
                  />
                </div>
              )
            )}
          </div>
        )}
      </Modal>
    </>
  );
};

export default VariableHelper;
